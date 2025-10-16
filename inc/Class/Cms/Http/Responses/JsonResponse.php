<?php
declare(strict_types=1);

namespace Cms\Http\Responses;

final class JsonResponse
{
    public static function ok(array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function err(string $message, int $status = 400, array $extra = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>$message,'extra'=>$extra], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
