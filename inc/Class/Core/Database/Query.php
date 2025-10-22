<?php
declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOStatement;

/**
 * Query – jednoduchý a čitelný query builder (SELECT/INSERT/UPDATE/DELETE),
 * joiny, where, group/having, order/limit, bezpečné bindy, transakce podle potřeby.
 */
final class Query
{
    private PDO $pdo;

    private ?string $table = null;
    private ?string $alias = null;

    private string $type = 'select';         // select|insert|update|delete
    private array $columns = ['*'];

    /** @var array<array{type:string,table:string,on?:array{left:string,op:string,right:string}}>> */
    private array $joins = [];

    /** @var array<string|array{bool:string,col?:string,op?:string,val?:mixed} > */
    private array $wheres = [];

    private array $groupBy = [];
    private array $having  = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    // --- INSERT stavy ---
    private array $insertData = [];          // single-row assoc insert
    private array $insertColumns = [];       // column-mode: ['col1','col2',...]
    /** @var array<int,array> */
    private array $insertRows = [];          // column-mode: [[v1,v2,...], [v1b,v2b,...]]

    private array $updateData = [];

    private int $paramCounter = 0;
    private array $bindings = [];

    public function __construct(PDO|\Core\Database\Connect $conn)
    {
        $this->pdo = $conn instanceof Connect ? $conn->pdo() : $conn;
    }

    // ---------------------------------------------------------
    // Základ
    // ---------------------------------------------------------
    public function table(string $table, ?string $alias = null): self
    {
        $this->table = $table;
        $this->alias = $alias;
        return $this;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->type    = 'select';
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * INSERT API:
     * - assoc data  => single-row INSERT (zpětně kompatibilní)
     * - list sloupců => multi-row režim s ->values([...]) ... ->execute()
     */
    public function insert(array $data): self
    {
        $this->type = 'insert';
        if (\function_exists('array_is_list') ? !array_is_list($data) : $this->isAssoc($data)) {
            // single-row assoc
            $this->insertData     = $data;
            $this->insertColumns  = [];
            $this->insertRows     = [];
        } else {
            // column-mode
            $this->insertData     = [];
            $this->insertColumns  = array_values($data);
            $this->insertRows     = [];
        }
        return $this;
    }

    /** Multi-row: přidej jeden řádek v pořadí z ->insert(['col1','col2',...]) */
    public function values(array $row): self
    {
        if ($this->type !== 'insert' || !$this->insertColumns) {
            throw new \LogicException('Call insert([col1,col2,...]) before values([...]).');
        }
        $vals = array_values($row);
        if (count($vals) !== count($this->insertColumns)) {
            throw new \InvalidArgumentException('VALUES count does not match INSERT column count.');
        }
        $this->insertRows[] = $vals;
        return $this;
    }

    /** Pohodlný single-row insert z asociativního pole (klíče = názvy sloupců). */
    public function insertRow(array $assoc): self
    {
        $this->type = 'insert';
        if (!$this->insertColumns && !$this->insertData) {
            $this->insertColumns = array_keys($assoc);
        }
        if ($this->insertColumns) {
            $row = [];
            foreach ($this->insertColumns as $c) {
                if (!array_key_exists($c, $assoc)) {
                    throw new \InvalidArgumentException("Missing value for column '$c' in insertRow().");
                }
                $row[] = $assoc[$c];
            }
            $this->insertRows[] = $row;
        } else {
            $this->insertData = $assoc;
        }
        return $this;
    }

    public function update(array $data): self
    {
        $this->type       = 'update';
        $this->updateData = $data;
        return $this;
    }

    public function delete(): self
    {
        $this->type = 'delete';
        return $this;
    }

    // ---------------------------------------------------------
    // Joiny
    // ---------------------------------------------------------
    public function join(string $table, string $left, string $op, string $right, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type'  => strtoupper($type),
            'table' => $table,
            'on'    => ['left' => $left, 'op' => $op, 'right' => $right],
        ];
        return $this;
    }

    public function leftJoin(string $table, string $left, string $op, string $right): self
    {
        return $this->join($table, $left, $op, $right, 'LEFT');
    }
    public function rightJoin(string $table, string $left, string $op, string $right): self
    {
        return $this->join($table, $left, $op, $right, 'RIGHT');
    }

    // ---------------------------------------------------------
    // Where / Having
    // ---------------------------------------------------------
    public function where(string|array|\Closure $col, ?string $op = null, mixed $val = null, string $bool = 'AND'): self
    {
        if ($col instanceof \Closure) {
            // Označ explicitně otevření/uzavření skupiny jedním tokenem,
            // ať to jde korektně rendrovat bez dvojitého AND/OR uvnitř.
            $this->wheres[] = strtoupper($bool) === 'OR' ? 'OR (' : 'AND (';
            $col($this);
            $this->wheres[] = ')';
            return $this;
        }

        if (is_array($col)) {
            foreach ($col as $k => $v) {
                $this->where((string)$k, '=', $v, $bool);
                $bool = 'AND';
            }
            return $this;
        }

        $this->wheres[] = [
            'bool' => strtoupper($bool) === 'OR' ? 'OR' : 'AND',
            'col'  => $col,
            'op'   => $op ?? '=',
            'val'  => $val,
        ];
        return $this;
    }

    public function orWhere(string|array|\Closure $col, ?string $op = null, mixed $val = null): self
    {
        return $this->where($col, $op, $val, 'OR');
    }

    public function whereIn(string $col, array $values, string $bool = 'AND', bool $not = false): self
    {
        if (!$values) {
            return $not ? $this->whereRaw('1=1', $bool) : $this->whereRaw('1=0', $bool);
        }
        $placeholders = [];
        foreach ($values as $v) {
            $placeholders[] = $this->bind($v);
        }
        $sql = sprintf('%s %s (%s)', $col, $not ? 'NOT IN' : 'IN', implode(',', $placeholders));
        return $this->whereRaw($sql, $bool);
    }

    public function whereNull(string $col, string $bool = 'AND', bool $not = false): self
    {
        $sql = sprintf('%s IS %sNULL', $col, $not ? 'NOT ' : '');
        return $this->whereRaw($sql, $bool);
    }

    public function whereLike(string $col, string $pattern, string $bool = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'bool' => strtoupper($bool) === 'OR' ? 'OR' : 'AND',
            'col'  => $col,
            'op'   => $not ? 'NOT LIKE' : 'LIKE',
            'val'  => $pattern,
        ];
        return $this;
    }

    public function whereRaw(string $raw, string $bool = 'AND'): self
    {
        $this->wheres[] = (strtoupper($bool) === 'OR' ? 'OR ' : 'AND ') . $raw;
        return $this;
    }

    public function having(string $col, string $op, mixed $val, string $bool = 'AND'): self
    {
        $this->having[] = [
            'bool' => strtoupper($bool) === 'OR' ? 'OR' : 'AND',
            'col'  => $col,
            'op'   => $op,
            'val'  => $val,
        ];
        return $this;
    }

    public function groupBy(array|string $cols): self
    {
        $this->groupBy = array_merge($this->groupBy, is_array($cols) ? $cols : [$cols]);
        return $this;
    }

    public function orderBy(string $expr, string $dir = 'ASC'): self
    {
        $this->orderBy[] = $expr . ' ' . (strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public function limit(int $n): self { $this->limit = max(0, $n); return $this; }
    public function offset(int $n): self { $this->offset = max(0, $n); return $this; }

    // ---------------------------------------------------------
    // Exec / Fetch
    // ---------------------------------------------------------
    public function toSql(): string
    {
        if ($this->table === null) {
            throw new \LogicException('No table set. Call ->table(...).');
        }

        return match ($this->type) {
            'select' => $this->buildSelect(),
            'insert' => $this->buildInsert(),
            'update' => $this->buildUpdate(),
            'delete' => $this->buildDelete(),
            default  => throw new \LogicException('Unknown query type: ' . $this->type)
        };
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function run(): PDOStatement
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt;
    }

    public function get(): array
    {
        return $this->run()->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit ??= 1;
        $row = $this->run()->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $column): mixed
    {
        $row = $this->first();
        return $row[$column] ?? null;
    }

    public function count(): int
    {
        $origColumns = $this->columns;
        $this->columns = ['COUNT(*) AS cnt'];
        $row = $this->first();
        $this->columns = $origColumns;
        return (int)($row['cnt'] ?? 0);
    }

    public function insertGetId(): string
    {
        if ($this->type !== 'insert') {
            throw new \LogicException('insertGetId() requires ->insert([...])');
        }
        $this->run();
        return $this->pdo->lastInsertId();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function execute(): int
    {
        $stmt = $this->run();
        return $stmt->rowCount();
    }

    // ---------------------------------------------------------
    // Transakce
    // ---------------------------------------------------------
    public function transactional(callable $fn): mixed
    {
        if (method_exists($this->pdo, 'inTransaction') && !$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            try {
                $res = $fn($this);
                $this->pdo->commit();
                return $res;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }
        return $fn($this);
    }

    // ---------------------------------------------------------
    // Buildery
    // ---------------------------------------------------------
    private function buildSelect(): string
    {
        $sql  = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->tableWithAlias();

        foreach ($this->joins as $j) {
            $sql .= sprintf(' %s JOIN %s ON %s %s %s',
                $j['type'],
                $j['table'],
                $j['on']['left'],
                $j['on']['op'],
                $j['on']['right']
            );
        }

        $sql .= $this->compileWhere();

        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having) {
            $parts = [];
            foreach ($this->having as $h) {
                $p = $this->bind($h['val']);
                $parts[] = sprintf('%s %s %s %s', $h['bool'], $h['col'], $h['op'], $p);
            }
            $sql .= ' HAVING ' . ltrim(implode(' ', $parts), 'ANDOR ');
        }

        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    private function buildInsert(): string
    {
        // single-row assoc
        if ($this->insertData) {
            $cols = array_keys($this->insertData);
            $place = [];
            foreach ($cols as $c) {
                $place[] = $this->bind($this->insertData[$c]);
            }
            return sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->tableWithAlias(false),
                implode(',', $cols),
                implode(',', $place)
            );
        }

        // column-mode + values()
        if ($this->insertColumns && $this->insertRows) {
            $cols = implode(',', $this->insertColumns);
            $rowsSql = [];
            foreach ($this->insertRows as $row) {
                $ph = [];
                foreach ($row as $v) {
                    $ph[] = $this->bind($v);
                }
                $rowsSql[] = '(' . implode(',', $ph) . ')';
            }
            return sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $this->tableWithAlias(false),
                $cols,
                implode(',', $rowsSql)
            );
        }

        throw new \LogicException('No insert data provided.');
    }

    private function buildUpdate(): string
    {
        if (!$this->updateData) {
            throw new \LogicException('No update data provided.');
        }
        $sets = [];
        foreach ($this->updateData as $col => $val) {
            $sets[] = sprintf('%s = %s', $col, $this->bind($val));
        }
        $sql = sprintf(
            'UPDATE %s SET %s',
            $this->tableWithAlias(false),
            implode(', ', $sets)
        );
        $sql .= $this->compileWhere(true); // ochrana před UPDATE bez WHERE
        return $sql;
    }

    private function buildDelete(): string
    {
        $sql = sprintf('DELETE FROM %s', $this->tableWithAlias(false));
        $sql .= $this->compileWhere(true);  // ochrana před DELETE bez WHERE
        return $sql;
    }

    private function tableWithAlias(bool $includeAlias = true): string
    {
        if ($this->alias && $includeAlias) {
            return $this->table . ' ' . $this->alias;
        }
        return (string)$this->table;
    }

    /**
     * Rendrování WHERE podmínek včetně skupin:
     * - tokeny 'AND (' / 'OR (' otevřou skupinu; první podmínka uvnitř je bez bool (žádné dvojité AND/OR)
     * - token ')' uzavře skupinu.
     */
    private function compileWhere(bool $force = false): string
    {
        if (!$this->wheres) {
            return $force ? ' WHERE 1=0' : '';
        }

        $parts = [];
        $afterOpen = false; // jsme bezprostředně po '(' -> další podmínka bez bool

        foreach ($this->wheres as $w) {
            if (is_string($w)) {
                $parts[] = $w;
                // detekuj otevření/uzavření skupiny
                $trim = rtrim($w);
                if ($trim === 'AND (' || $trim === 'OR (') {
                    $afterOpen = true;
                } elseif ($trim === ')') {
                    $afterOpen = false;
                }
                continue;
            }

            // standardní podmínka
            $p = $this->bind($w['val']);
            if ($afterOpen) {
                // první podmínka uvnitř závorky – bez bool
                $parts[] = sprintf('%s %s %s', $w['col'], $w['op'], $p);
                $afterOpen = false;
            } else {
                $parts[] = sprintf('%s %s %s %s', $w['bool'], $w['col'], $w['op'], $p);
            }
        }

        $sql = ' WHERE ' . ltrim(implode(' ', $parts), 'ANDOR ');
        return $sql;
    }

    private function bind(mixed $value): string
    {
        $name = ':p' . (++$this->paramCounter);
        $this->bindings[$name] = $value;
        return $name;
    }

    // ---------------------------------------------------------
    // Paginace
    // ---------------------------------------------------------
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->countForPagination();

        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        $pages = (int)ceil($total / $perPage);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
            'hasPrev'  => $page > 1,
            'hasNext'  => $page < $pages,
        ];
    }

    /**
     * Bezpečný COUNT pro aktuální SELECT:
     * - nemutuje původní builder (bindings, counter, order/limit/offset se vrací zpět)
     * - při GROUP BY/HAVING použije subselect.
     */
    private function countForPagination(): int
    {
        // snapshot stavu
        $origBindings    = $this->bindings;
        $origCounter     = $this->paramCounter;
        $origColumns     = $this->columns;
        $origOrder       = $this->orderBy;
        $origLimit       = $this->limit;
        $origOffset      = $this->offset;

        try {
            $hasGrouping = !empty($this->groupBy) || !empty($this->having);

            // pro COUNT ignorujeme ORDER/LIMIT/OFFSET
            $this->orderBy = [];
            $this->limit   = null;
            $this->offset  = null;

            if ($hasGrouping) {
                $sql = 'SELECT COUNT(*) AS cnt FROM (' . $this->buildSelect() . ') _sub';
                $bindings = $this->bindings;
            } else {
                $this->columns = ['COUNT(*) AS cnt'];
                $sql = $this->buildSelectNoOrderLimit();
                $bindings = $this->bindings;
                $this->columns = $origColumns;
            }

            $stmt = $this->pdo->prepare($sql);
            foreach ($bindings as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $row = $stmt->fetch();

            return (int)($row['cnt'] ?? 0);
        } finally {
            // obnov stav
            $this->bindings     = $origBindings;
            $this->paramCounter = $origCounter;
            $this->columns      = $origColumns;
            $this->orderBy      = $origOrder;
            $this->limit        = $origLimit;
            $this->offset       = $origOffset;
        }
    }

    private function buildSelectNoOrderLimit(): string
    {
        $sql  = 'SELECT ' . implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->tableWithAlias();

        foreach ($this->joins as $j) {
            $sql .= sprintf(' %s JOIN %s ON %s %s %s',
                $j['type'],
                $j['table'],
                $j['on']['left'],
                $j['on']['op'],
                $j['on']['right']
            );
        }

        $sql .= $this->compileWhere();

        if ($this->groupBy) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having) {
            $parts = [];
            foreach ($this->having as $h) {
                $p = $this->bind($h['val']);
                $parts[] = sprintf('%s %s %s %s', $h['bool'], $h['col'], $h['op'], $p);
            }
            $sql .= ' HAVING ' . ltrim(implode(' ', $parts), 'ANDOR ');
        }

        return $sql;
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------
    private function isAssoc(array $arr): bool
    {
        foreach (array_keys($arr) as $k) {
            if (!is_int($k)) return true;
        }
        return false;
    }
}
