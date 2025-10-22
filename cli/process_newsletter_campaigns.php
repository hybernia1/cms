#!/usr/bin/env php
<?php
declare(strict_types=1);

use Cms\Admin\Domain\Entities\NewsletterCampaign;
use Cms\Admin\Domain\Entities\NewsletterCampaignSchedule;
use Cms\Admin\Domain\Repositories\NewsletterCampaignRepository;
use Cms\Admin\Domain\Repositories\NewsletterCampaignScheduleRepository;
use Cms\Admin\Domain\Services\NewsletterService;
use Cms\Admin\Mail\MailService;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Core\Database\Init as DB;

require_once __DIR__ . '/../load.php';

function cli_print(string $message): void
{
    fwrite(STDOUT, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message));
}

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Konfigurační soubor config.php nebyl nalezen.\n");
    exit(1);
}

/** @var array<string,mixed> $config */
$config = require $configFile;
DB::boot($config);

$limitArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limitArg = (int) substr($arg, strlen('--limit='));
    }
}

$settings = new CmsSettings();
$newsletterService = new NewsletterService();
$scheduleRepository = new NewsletterCampaignScheduleRepository();
$campaignRepository = new NewsletterCampaignRepository();
$mailService = new MailService($settings);

$campaignLimit = $settings->newsletterCampaignLimit();
$batchSize = $limitArg !== null && $limitArg > 0 ? min($limitArg, $campaignLimit) : $campaignLimit;
$now = DateTimeFactory::nowString();

$dueSchedules = $scheduleRepository->dueSchedules($now);
if ($dueSchedules === []) {
    cli_print('Žádné naplánované kampaně nejsou připravené ke zpracování.');
    exit(0);
}

cli_print(sprintf('Nalezeno %d naplánovaných kampaní ke zpracování.', count($dueSchedules)));

foreach ($dueSchedules as $schedule) {
    process_schedule(
        $schedule,
        $scheduleRepository,
        $campaignRepository,
        $newsletterService,
        $mailService,
        $settings,
        $batchSize
    );
}

cli_print('Zpracování dokončeno.');
exit(0);

/**
 * @return list<array{id:int,email:string}>
 */
function load_recipients(NewsletterService $service, int $limit, int $offset): array
{
    $recipients = $service->confirmedEmails($limit, $offset);
    return array_values(array_map(static function (array $row): array {
        return [
            'id'    => isset($row['id']) ? (int) $row['id'] : 0,
            'email' => (string) ($row['email'] ?? ''),
        ];
    }, $recipients));
}

function process_schedule(
    NewsletterCampaignSchedule $schedule,
    NewsletterCampaignScheduleRepository $scheduleRepository,
    NewsletterCampaignRepository $campaignRepository,
    NewsletterService $newsletterService,
    MailService $mailService,
    CmsSettings $settings,
    int $batchSize
): void {
    $campaign = $campaignRepository->find($schedule->campaignId());
    if (!$campaign) {
        cli_print(sprintf('Kampaň #%d nebyla nalezena, plán bude uzavřen.', $schedule->campaignId()));
        finalize_schedule($schedule, $scheduleRepository, DateTimeFactory::nowString());
        return;
    }

    $now = DateTimeFactory::nowString();
    $startAt = $schedule->startAt() ?? $now;

    $recipientsCount = $campaign->recipientsCount();
    if ($recipientsCount <= 0) {
        $recipientsCount = $newsletterService->confirmedCount();
        $campaign = new NewsletterCampaign(
            $campaign->id(),
            $campaign->subject(),
            $campaign->body(),
            $campaign->status(),
            $recipientsCount,
            $campaign->sentCount(),
            $campaign->failedCount(),
            $campaign->createdBy(),
            $campaign->createdAt(),
            $now,
            $campaign->sentAt(),
        );
        $campaign = $campaignRepository->update($campaign);
    }

    $attemptsDone = $schedule->attempts();
    if ($attemptsDone >= $schedule->maxAttempts()) {
        cli_print(sprintf('Kampaň #%d dosáhla maximálního počtu pokusů.', $campaign->id()));
        finalize_schedule($schedule, $scheduleRepository, $now);
        return;
    }

    $offset = $attemptsDone * $batchSize;
    if ($offset >= $recipientsCount) {
        cli_print(sprintf('Kampaň #%d je již kompletně zpracována.', $campaign->id()));
        finalize_schedule($schedule, $scheduleRepository, $now);
        complete_campaign($campaignRepository, $campaign, $campaign->sentCount(), $campaign->failedCount(), true, $now);
        return;
    }

    $recipients = load_recipients($newsletterService, $batchSize, $offset);
    if ($recipients === []) {
        cli_print(sprintf('Pro kampaň #%d nejsou dostupní odběratelé.', $campaign->id()));
        finalize_schedule($schedule, $scheduleRepository, $now);
        return;
    }

    cli_print(sprintf('Spouštím kampaň #%d (%s) – pokus %d/%d.', $campaign->id(), $campaign->subject(), $attemptsDone + 1, $schedule->maxAttempts()));

    $scheduleRunning = new NewsletterCampaignSchedule(
        $schedule->id(),
        $schedule->campaignId(),
        NewsletterCampaignSchedule::STATUS_RUNNING,
        $startAt,
        $schedule->endAt(),
        $schedule->intervalMinutes(),
        $schedule->maxAttempts(),
        $attemptsDone + 1,
        null,
        $now,
        $schedule->createdAt(),
        $now,
    );
    $scheduleRepository->update($scheduleRunning);

    $campaignRunning = new NewsletterCampaign(
        $campaign->id(),
        $campaign->subject(),
        $campaign->body(),
        NewsletterCampaign::STATUS_SENDING,
        $recipientsCount,
        $campaign->sentCount(),
        $campaign->failedCount(),
        $campaign->createdBy(),
        $campaign->createdAt(),
        $now,
        $campaign->sentAt(),
    );
    $campaignRunning = $campaignRepository->update($campaignRunning);

    $sent = 0;
    $failed = 0;
    foreach ($recipients as $recipient) {
        $email = $recipient['email'] ?? '';
        $subscriberId = $recipient['id'] ?? 0;
        if ($email === '' || $subscriberId <= 0) {
            continue;
        }

        $ok = $mailService->send($email, $campaignRunning->subject(), $campaignRunning->body(), null, strip_tags($campaignRunning->body()));
        if ($ok) {
            $sent++;
        } else {
            $failed++;
        }
        $newsletterService->logCampaignAttempt($campaignRunning, $subscriberId, $ok, $ok ? 'OK' : null, $ok ? null : 'Mailer did not confirm delivery.');
    }

    $processedCount = $offset + count($recipients);
    $totalSent = $campaignRunning->sentCount() + $sent;
    $totalFailed = $campaignRunning->failedCount() + $failed;
    $allProcessed = $processedCount >= $recipientsCount;

    $campaignStatus = NewsletterCampaign::STATUS_SENDING;
    if ($allProcessed) {
        if ($totalSent === 0 && $totalFailed > 0) {
            $campaignStatus = NewsletterCampaign::STATUS_FAILED;
        } else {
            $campaignStatus = NewsletterCampaign::STATUS_COMPLETED;
        }
    }

    complete_campaign(
        $campaignRepository,
        $campaignRunning,
        $totalSent,
        $totalFailed,
        $campaignStatus === NewsletterCampaign::STATUS_COMPLETED,
        $now,
        $campaignStatus
    );

    $remainingRecipients = max(0, $recipientsCount - $processedCount);
    $attemptsLimitReached = ($scheduleRunning->attempts() >= $scheduleRunning->maxAttempts());
    $endAt = $scheduleRunning->endAt();
    $nextRun = compute_next_run($scheduleRunning, $now);

    if ($remainingRecipients === 0) {
        finalize_schedule($scheduleRunning, $scheduleRepository, $now, $endAt ?: $now);
        cli_print(sprintf('Kampaň #%d úspěšně dokončena (odesláno: %d, chyb: %d).', $campaign->id(), $totalSent, $totalFailed));
        return;
    }

    if ($attemptsLimitReached || ($endAt !== null && $nextRun !== null && $nextRun > DateTimeFactory::fromStorage($endAt))) {
        finalize_schedule($scheduleRunning, $scheduleRepository, $now, $now);
        cli_print(sprintf('Kampaň #%d byla ukončena bez kompletního doručení (odesláno: %d, zbývá: %d).', $campaign->id(), $totalSent, $remainingRecipients));
        complete_campaign($campaignRepository, $campaignRunning, $totalSent, $totalFailed, false, $now, NewsletterCampaign::STATUS_FAILED);
        return;
    }

    if ($nextRun === null) {
        finalize_schedule($scheduleRunning, $scheduleRepository, $now, $now);
        cli_print(sprintf('Kampaň #%d nemá platný další termín a byla ukončena.', $campaign->id()));
        return;
    }

    $nextRunString = DateTimeFactory::formatForStorage($nextRun);
    $scheduled = new NewsletterCampaignSchedule(
        $scheduleRunning->id(),
        $scheduleRunning->campaignId(),
        NewsletterCampaignSchedule::STATUS_SCHEDULED,
        $scheduleRunning->startAt() ?? $startAt,
        $scheduleRunning->endAt(),
        $scheduleRunning->intervalMinutes(),
        $scheduleRunning->maxAttempts(),
        $scheduleRunning->attempts(),
        $nextRunString,
        $scheduleRunning->lastRunAt(),
        $scheduleRunning->createdAt(),
        $now,
    );
    $scheduleRepository->update($scheduled);

    cli_print(sprintf('Kampaň #%d naplánována na další běh v %s (zbývá %d adres).', $campaign->id(), $settings->formatDateTime($nextRun), $remainingRecipients));
}

function compute_next_run(NewsletterCampaignSchedule $schedule, string $reference): ?\DateTimeImmutable
{
    $interval = max(1, $schedule->intervalMinutes());
    $lastRun = DateTimeFactory::fromStorage($schedule->lastRunAt() ?? $reference);
    if ($lastRun === null) {
        return null;
    }

    return $lastRun->modify(sprintf('+%d minutes', $interval));
}

function finalize_schedule(
    NewsletterCampaignSchedule $schedule,
    NewsletterCampaignScheduleRepository $repository,
    string $completedAt,
    ?string $endAt = null
): void {
    $scheduleCompleted = new NewsletterCampaignSchedule(
        $schedule->id(),
        $schedule->campaignId(),
        NewsletterCampaignSchedule::STATUS_COMPLETED,
        $schedule->startAt() ?? $completedAt,
        $endAt ?? $schedule->endAt() ?? $completedAt,
        $schedule->intervalMinutes(),
        $schedule->maxAttempts(),
        $schedule->attempts(),
        null,
        $schedule->lastRunAt(),
        $schedule->createdAt(),
        $completedAt,
    );
    $repository->update($scheduleCompleted);
}

function complete_campaign(
    NewsletterCampaignRepository $repository,
    NewsletterCampaign $campaign,
    int $sent,
    int $failed,
    bool $finished,
    string $updatedAt,
    ?string $forcedStatus = null
): void {
    $status = $forcedStatus ?? ($finished ? NewsletterCampaign::STATUS_COMPLETED : NewsletterCampaign::STATUS_SENDING);
    $sentAt = $finished && $status === NewsletterCampaign::STATUS_COMPLETED ? $updatedAt : $campaign->sentAt();

    $updated = new NewsletterCampaign(
        $campaign->id(),
        $campaign->subject(),
        $campaign->body(),
        $status,
        $campaign->recipientsCount(),
        $sent,
        $failed,
        $campaign->createdBy(),
        $campaign->createdAt(),
        $updatedAt,
        $sentAt,
    );

    $repository->update($updated);
}
