<?php

namespace tpext\common;

/**
 * MySQL SQL → PostgreSQL SQL 转换器
 *
 * 将 MySQL 单条 SQL 解析为结构化数据，再生成 PostgreSQL 兼容的 SQL 语句。
 * 每个 SQL 类型都有对应的结构化输出格式，方便精确控制转换逻辑。
 */
class Pgsql
{
    /**
     * MySQL 类型 → PostgreSQL 类型映射
     */
    protected static $typeMap = [
        'tinyint' => 'smallint',
        'smallint' => 'smallint',
        'mediumint' => 'integer',
        'int' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigint',
        'float' => 'real',
        'double' => 'double precision',
        'real' => 'real',
        'decimal' => 'numeric',
        'dec' => 'numeric',
        'numeric' => 'numeric',
        'datetime' => 'timestamp(0)',
        'year' => 'smallint',
        'json' => 'jsonb',
        'tinytext' => 'text',
        'mediumtext' => 'text',
        'longtext' => 'text',
        'tinyblob' => 'bytea',
        'blob' => 'bytea',
        'mediumblob' => 'bytea',
        'longblob' => 'bytea',
        'binary' => 'bytea',
        'varbinary' => 'bytea',
    ];

    /**
     * 支持长度/精度参数的类型
     */
    protected static $typesWithLength = [
        'char',
        'varchar',
        'numeric',
        'decimal',
        'dec',
    ];

    // ===================================================================
    //  公开接口
    // ===================================================================

    /**
     * 将 MySQL 单条 SQL 转换为 PostgreSQL 兼容语法，返回一个或多个 SQL 语句
     *
     * @param  string $sql
     * @param  array  &$state 状态: ['serialColumns'=>[], 'pendingSyncs'=>[]]
     * @return string[]
     */
    public static function mysqlToPostgresql($sql, array &$state)
    {
        $parsed = static::parse($sql);
        return static::toSql($parsed, $state);
    }

    /**
     * 从 state 中构建序列同步 SETVAL 语句
     *
     * @param  array    $state
     * @return string[] 如 ["SELECT setval(...) FROM \"table\""]
     */
    public static function buildSequenceSyncs(array $state)
    {
        $sqls = [];
        foreach ($state['pendingSyncs'] as $table => $col) {
            // pg_get_serial_sequence 接收字符串字面量（单引号），非标识符（双引号）
            $sqls[] = "SELECT setval(pg_get_serial_sequence('{$table}', '{$col}'), "
                . "COALESCE(MAX(\"{$col}\"), 1)) FROM \"{$table}\"";
        }
        return $sqls;
    }

    /**
     * 解析 MySQL SQL 为结构化数据
     *
     * @param  string $sql
     * @return array
     */
    public static function parse($sql)
    {
        $sql = trim($sql);

        if (preg_match('/^\s*CREATE\s+TABLE/i', $sql)) {
            return static::parseCreateTable($sql);
        }
        if (preg_match('/^\s*INSERT\s+/i', $sql)) {
            return static::parseInsert($sql);
        }
        if (preg_match('/^\s*ALTER\s+TABLE/i', $sql)) {
            return static::parseAlterTable($sql);
        }
        if (preg_match('/^\s*DROP\s+TABLE/i', $sql)) {
            return static::parseDropTable($sql);
        }
        if (preg_match('/^\s*UPDATE\s+/i', $sql)) {
            return static::parseUpdate($sql);
        }

        return ['type' => 'raw', 'sql' => $sql];
    }

    /**
     * 将结构化数据转换为 PostgreSQL SQL 语句数组
     *
     * @param  array    $data
     * @return string[]
     */
    public static function toSql(array $data, array &$state)
    {
        $type = $data['type'] ?? 'raw';

        switch ($type) {
            case 'create_table':
                return static::toCreateTableSql($data, $state);
            case 'insert':
                return static::toInsertSql($data, $state);
            case 'alter_table':
                return static::toAlterTableSql($data, $state);
            case 'drop_table':
                return static::toDropTableSql($data);
            case 'update':
                return static::toUpdateSql($data);
            default:
                return [$data['sql'] ?? ''];
        }
    }

    // ===================================================================
    //  SQL 生成器：结构化数据 → PostgreSQL SQL
    // ===================================================================

    /**
     * CREATE TABLE → PostgreSQL
     */
    protected static function toCreateTableSql(array $data, array &$state)
    {
        $table = $data['table'];
        $fields = $data['fields'] ?? [];
        $keys = $data['keys'] ?? [];
        $comment = $data['comment'] ?? '';
        $ifNotExists = !empty($data['if_not_exists']);

        $lines = [];
        $extras = [];
        $comments = [];

        // 字段定义
        foreach ($fields as $f) {
            $line = '"' . $f['name'] . '" ' . static::buildPgColumnType($f);

            // SERIAL 自带 NOT NULL，不需额外声明
            if (empty($f['auto_inc']) && empty($f['nullable'])) {
                $line .= ' NOT NULL';
            }
            if (empty($f['auto_inc'])) {
                $default = static::buildDefaultValue($f);
                if ($default !== null) {
                    $line .= ' DEFAULT ' . $default;
                }
            }

            $lines[] = $line;

            if (!empty($f['comment'])) {
                $comments[] = 'COMMENT ON COLUMN "' . $table . '"."' . $f['name'] . '" IS '
                    . static::quotePgString($f['comment']);
            }
        }

        // 主键
        $pkColumns = [];
        foreach ($keys as $k) {
            if ($k['type'] === 'pk') {
                $pkColumns = array_merge($pkColumns, $k['columns']);
                $lines[] = 'PRIMARY KEY ("' . implode('", "', $k['columns']) . '")';
            }
        }

        // 外键（保留在表内）
        foreach ($keys as $k) {
            if ($k['type'] === 'foreign') {
                $line = 'FOREIGN KEY ("' . implode('", "', $k['columns']) . '") '
                    . 'REFERENCES "' . $k['ref_table'] . '" ("' . implode('", "', $k['ref_columns']) . '")';
                if (!empty($k['on_delete'])) {
                    $line .= ' ON DELETE ' . $k['on_delete'];
                }
                if (!empty($k['on_update'])) {
                    $line .= ' ON UPDATE ' . $k['on_update'];
                }
                $lines[] = $line;
            }
        }

        // 检查约束
        foreach ($keys as $k) {
            if ($k['type'] === 'check') {
                $lines[] = 'CHECK (' . $k['expression'] . ')';
            }
        }

        // 唯一/普通索引
        foreach ($keys as $k) {
            if ($k['type'] === 'uk') {
                // UK 全部内联：PG 支持 CONSTRAINT "name" UNIQUE (...) 在 CREATE TABLE 内部
                if (empty($k['name'])) {
                    $lines[] = 'UNIQUE ("' . implode('", "', $k['columns']) . '")';
                } else {
                    $lines[] = 'CONSTRAINT "' . $k['name'] . '" UNIQUE ("' . implode('", "', $k['columns']) . '")';
                }
            } elseif ($k['type'] === 'index' || $k['type'] === 'fulltext' || $k['type'] === 'spatial') {
                // INDEX 不可内联，加表前缀避免跨表冲突
                $name = $table . '_' . (!empty($k['name']) ? $k['name'] : 'idx_' . implode('_', $k['columns']));
                $extras[] = 'CREATE INDEX IF NOT EXISTS "' . $name . '" ON "' . $table
                    . '" ("' . implode('", "', $k['columns']) . '")';
            }
        }

        // 表注释
        if ($comment) {
            $comments[] = 'COMMENT ON TABLE "' . $table . '" IS ' . static::quotePgString($comment);
        }

        $ifStr = $ifNotExists ? 'IF NOT EXISTS ' : '';
        $header = 'CREATE TABLE ' . $ifStr . '"' . $table . '" (';
        $result = $header . "\n  " . implode(",\n  ", $lines) . "\n)";

        // 记录自增列，用于后续 INSERT 后同步序列
        foreach ($fields as $f) {
            if (!empty($f['auto_inc'])) {
                $state['serialColumns'][$table][] = $f['name'];
            }
        }

        $statements = [$result];
        foreach ($extras as $extra) {
            $statements[] = $extra;
        }
        foreach ($comments as $c) {
            $statements[] = $c;
        }
        $statements[] = 'ANALYZE "' . $table . '"';

        return $statements;
    }

    /**
     * INSERT → PostgreSQL
     */
    protected static function toInsertSql(array $data, array &$state)
    {
        $table = $data['table'];
        $fields = $data['fields'] ?? [];
        $ignore = !empty($data['ignore']);

        // 检测 INSERT 是否显式指定了自增列的值，标记待同步序列
        if ($fields) {
            $syncCol = null;

            // 优先从 CREATE TABLE 追踪到的自增列中匹配
            if (isset($state['serialColumns'][$table])) {
                foreach ($state['serialColumns'][$table] as $col) {
                    if (in_array($col, $fields)) {
                        $syncCol = $col;
                        break;
                    }
                }
            }

            // 兜底：无建表语句时，按约定 values 中含 id 字段即认为指定了自增列
            if ($syncCol === null && in_array('id', $fields)) {
                $syncCol = 'id';
            }

            if ($syncCol !== null) {
                $state['pendingSyncs'][$table] = $syncCol;
            }
        }

        $sql = 'INSERT INTO "' . $table . '" ';
        if ($fields) {
            $sql .= '("' . implode('", "', $fields) . '") ';
        }
        $sql .= $data['values_sql'];

        if ($ignore) {
            $sql = rtrim($sql) . ' ON CONFLICT DO NOTHING';
        }

        return [$sql];
    }

    /**
     * ALTER TABLE → PostgreSQL
     */
    protected static function toAlterTableSql(array $data, array &$state)
    {
        $table = $data['table'];
        $fields = $data['fields'] ?? [];
        $keys = $data['keys'] ?? [];
        $statements = [];

        foreach ($fields as $f) {
            $action = $f['action'] ?? '';

            if ($action === 'add') {
                $line = 'ALTER TABLE "' . $table . '" ADD COLUMN "' . $f['name'] . '" '
                    . static::buildPgColumnType($f);
                // SERIAL 自带 NOT NULL，不需额外声明
                if (empty($f['auto_inc']) && empty($f['nullable'])) {
                    $line .= ' NOT NULL';
                }
                if (empty($f['auto_inc'])) {
                    $default = static::buildDefaultValue($f);
                    if ($default !== null) {
                        $line .= ' DEFAULT ' . $default;
                    }
                }
                if (!empty($f['auto_inc'])) {
                    $state['serialColumns'][$table][] = $f['name'];
                }
                $statements[] = $line;

                if (!empty($f['comment'])) {
                    $statements[] = 'COMMENT ON COLUMN "' . $table . '"."' . $f['name'] . '" IS '
                        . static::quotePgString($f['comment']);
                }
            } elseif ($action === 'drop') {
                $statements[] = 'ALTER TABLE "' . $table . '" DROP COLUMN "' . $f['name'] . '"';
            } elseif ($action === 'change') {
                // CHANGE = 重命名 + 改类型
                if (!empty($f['old_name']) && $f['old_name'] !== $f['name']) {
                    $statements[] = 'ALTER TABLE "' . $table . '" RENAME COLUMN "'
                        . $f['old_name'] . '" TO "' . $f['name'] . '"';
                }

                $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" TYPE '
                    . static::buildPgColumnType($f);

                if (array_key_exists('nullable', $f)) {
                    if (empty($f['nullable'])) {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" SET NOT NULL';
                    } else {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" DROP NOT NULL';
                    }
                }

                if (array_key_exists('default', $f)) {
                    $default = static::buildDefaultValue($f);
                    if ($default !== null) {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" SET DEFAULT ' . $default;
                    } else {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" DROP DEFAULT';
                    }
                }

                if (!empty($f['comment'])) {
                    $statements[] = 'COMMENT ON COLUMN "' . $table . '"."' . $f['name'] . '" IS '
                        . static::quotePgString($f['comment']);
                }
            } elseif ($action === 'modify') {
                // MODIFY = 只改类型/属性，不改名
                $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" TYPE '
                    . static::buildPgColumnType($f);

                if (array_key_exists('nullable', $f)) {
                    if (empty($f['nullable'])) {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" SET NOT NULL';
                    } else {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" DROP NOT NULL';
                    }
                }

                if (array_key_exists('default', $f)) {
                    $default = static::buildDefaultValue($f);
                    if ($default !== null) {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" SET DEFAULT ' . $default;
                    } else {
                        $statements[] = 'ALTER TABLE "' . $table . '" ALTER COLUMN "' . $f['name'] . '" DROP DEFAULT';
                    }
                }

                if (!empty($f['comment'])) {
                    $statements[] = 'COMMENT ON COLUMN "' . $table . '"."' . $f['name'] . '" IS '
                        . static::quotePgString($f['comment']);
                }
            }
        }

        // 键操作
        foreach ($keys as $k) {
            $action = $k['action'] ?? '';

            if ($action === 'add') {
                switch ($k['type']) {
                    case 'pk':
                        $statements[] = 'ALTER TABLE "' . $table . '" ADD PRIMARY KEY ("'
                            . implode('", "', $k['columns']) . '")';
                        break;
                    case 'uk':
                        if (empty($k['name'])) {
                            $statements[] = 'ALTER TABLE "' . $table . '" ADD UNIQUE ("'
                                . implode('", "', $k['columns']) . '")';
                        } else {
                            $statements[] = 'ALTER TABLE "' . $table . '" ADD CONSTRAINT "'
                                . $k['name'] . '" UNIQUE ("' . implode('", "', $k['columns']) . '")';
                        }
                        break;
                    case 'index':
                    case 'fulltext':
                    case 'spatial':
                        $name = $table . '_' . (!empty($k['name']) ? $k['name'] : implode('_', $k['columns']));
                        $statements[] = 'CREATE INDEX IF NOT EXISTS "' . $name . '" ON "' . $table
                            . '" ("' . implode('", "', $k['columns']) . '")';
                        break;
                    case 'foreign':
                        $line = 'ALTER TABLE "' . $table . '" ADD FOREIGN KEY ("'
                            . implode('", "', $k['columns']) . '") REFERENCES "'
                            . $k['ref_table'] . '" ("' . implode('", "', $k['ref_columns']) . '")';
                        if (!empty($k['on_delete'])) {
                            $line .= ' ON DELETE ' . $k['on_delete'];
                        }
                        if (!empty($k['on_update'])) {
                            $line .= ' ON UPDATE ' . $k['on_update'];
                        }
                        $statements[] = $line;
                        break;
                }
            } elseif ($action === 'drop') {
                switch ($k['type']) {
                    case 'pk':
                        $statements[] = 'ALTER TABLE "' . $table . '" DROP CONSTRAINT "' . $table . '_pkey"';
                        break;
                    case 'index':
                    case 'uk':
                    case 'fulltext':
                    case 'spatial':
                        if (!empty($k['name'])) {
                            $statements[] = 'DROP INDEX "' . $k['name'] . '"';
                        }
                        break;
                    case 'foreign':
                        if (!empty($k['name'])) {
                            $statements[] = 'ALTER TABLE "' . $table . '" DROP CONSTRAINT "' . $k['name'] . '"';
                        }
                        break;
                }
            }
        }

        return $statements ?: ['SELECT 1'];
    }

    /**
     * DROP TABLE → PostgreSQL
     */
    protected static function toDropTableSql(array $data)
    {
        $table = $data['table'];
        $ifExists = !empty($data['if_exists']);

        $ifStr = $ifExists ? 'IF EXISTS ' : '';
        return ['DROP TABLE ' . $ifStr . '"' . $table . '"'];
    }

    /**
     * UPDATE → PostgreSQL
     */
    protected static function toUpdateSql(array $data)
    {
        $sql = $data['sql'];
        $sql = preg_replace('/`([^`]*)`/', '"$1"', $sql);
        return [$sql];
    }

    // ===================================================================
    //  解析器：MySQL SQL → 结构化数据
    // ===================================================================

    /**
     * 解析 CREATE TABLE
     *
     * 返回结构:
     * [
     *   'type'          => 'create_table',
     *   'table'         => 'table_name',
     *   'if_not_exists' => false,
     *   'fields'        => [ ['name','type','length','default','nullable','auto_inc','unsigned','comment',...], ... ],
     *   'keys'          => [ ['name','type'=>'pk|uk|index|fulltext|spatial|foreign|check','columns'=>[],...], ... ],
     *   'comment'       => 'table comment',
     * ]
     */
    protected static function parseCreateTable($sql)
    {
        // 提取 IF NOT EXISTS
        $ifNotExists = (bool) preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $sql);

        // 提取表名（支持反引号、双引号、裸名）
        if (!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\[]?(\w+)[`"\]]?/i', $sql, $m)) {
            return ['type' => 'raw', 'sql' => $sql];
        }
        $tableName = $m[1];

        // 找到第一个 ( 和匹配的 )
        $firstParen = strpos($sql, '(');
        if ($firstParen === false) {
            return ['type' => 'raw', 'sql' => $sql];
        }

        $closeParen = static::findMatchingParen($sql, $firstParen);
        if ($closeParen === false) {
            return ['type' => 'raw', 'sql' => $sql];
        }

        $body = substr($sql, $firstParen + 1, $closeParen - $firstParen - 1);
        $footer = trim(substr($sql, $closeParen + 1));

        // 分割字段/约束定义
        $defs = static::splitByComma($body);

        $fields = [];
        $keys = [];
        $comment = '';

        foreach ($defs as $def) {
            $trimmed = trim($def);
            if ($trimmed === '') {
                continue;
            }

            // 判断是约束/键定义还是列定义
            if (preg_match('/^\s*(?:PRIMARY\s+KEY|UNIQUE\s*(?:INDEX|KEY)?|INDEX|KEY|FULLTEXT|SPATIAL|CONSTRAINT|CHECK|FOREIGN\s+KEY)/i', $trimmed)) {
                $key = static::parseKeyDef($trimmed);
                if ($key) {
                    $keys[] = $key;
                }
            } else {
                $field = static::parseColumnDef($trimmed);
                if ($field) {
                    $fields[] = $field;
                }
            }
        }

        // 提取表注释
        if (preg_match("/COMMENT\s*=\s*'((?:[^'\\\\]|\\\\.)*)'/i", $footer, $tm)) {
            $comment = str_replace("\\'", "'", $tm[1]);
        }

        return [
            'type' => 'create_table',
            'table' => $tableName,
            'if_not_exists' => $ifNotExists,
            'fields' => $fields,
            'keys' => $keys,
            'comment' => $comment,
        ];
    }

    /**
     * 解析 INSERT
     *
     * 返回结构:
     * [
     *   'type'       => 'insert',
     *   'table'      => 'table_name',
     *   'fields'     => ['col1', 'col2', ...],
     *   'ignore'     => false,
     *   'values_sql' => 'VALUES (...)',
     * ]
     */
    protected static function parseInsert($sql)
    {
        $ignore = (bool) preg_match('/^\s*INSERT\s+IGNORE\s+/i', $sql);
        $clean = preg_replace('/^\s*INSERT\s+(?:IGNORE\s+)?/i', 'INSERT ', $sql);

        // 提取表名
        if (!preg_match('/INSERT\s+(?:INTO\s+)?[`"\[]?(\w+)[`"\]]?/i', $clean, $m)) {
            return ['type' => 'raw', 'sql' => $sql];
        }
        $table = $m[1];

        // 提取字段列表 (col1, col2, ...) 在 VALUES 之前
        $fields = [];
        $valuesSql = '';

        if (preg_match('/INSERT\s+INTO\s+[`"\[]?\w+[`"\]]?\s*\(([^)]+)\)\s*(VALUES\s+.+)/is', $clean, $m)) {
            $fieldList = $m[1];
            $valuesSql = $m[2];
            // 按逗号分割字段名
            foreach (explode(',', $fieldList) as $f) {
                $f = trim($f);
                $f = trim($f, '`"[] ');
                if ($f !== '') {
                    $fields[] = $f;
                }
            }
        } elseif (preg_match('/INSERT\s+INTO\s+[`"\[]?\w+[`"\]]?\s*(VALUES\s+.+)/is', $clean, $m)) {
            $valuesSql = $m[1];
        }

        return [
            'type' => 'insert',
            'table' => $table,
            'fields' => $fields,
            'ignore' => $ignore,
            'values_sql' => rtrim(trim($valuesSql), ';'),
        ];
    }

    /**
     * 解析 ALTER TABLE
     *
     * 返回结构:
     * [
     *   'type'   => 'alter_table',
     *   'table'  => 'table_name',
     *   'fields' => [ ['name','old_name','type','length','default','nullable','unsigned','comment','action'=>'add|modify|change|drop',...], ... ],
     *   'keys'   => [ ['name','type'=>'pk|uk|index|fulltext|spatial|foreign','columns'=>[],'action'=>'add|drop',...], ... ],
     * ]
     */
    protected static function parseAlterTable($sql)
    {
        // 提取表名
        if (!preg_match('/ALTER\s+TABLE\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $m)) {
            return ['type' => 'raw', 'sql' => $sql];
        }
        $table = $m[1];

        // 移除表名后剩余的 alter 操作部分
        $rest = substr($sql, strlen($m[0]));
        $rest = trim($rest);

        $data = [
            'type' => 'alter_table',
            'table' => $table,
            'fields' => [],
            'keys' => [],
        ];

        // ======== 键操作（先匹配，避免被列操作误匹配） ========

        // ADD CONSTRAINT ... FOREIGN KEY
        if (preg_match('/^\s*ADD\s+CONSTRAINT\s+[`"\[]?(\w+)[`"\]]?\s+FOREIGN\s+KEY\s*(\([^)]+\))\s*REFERENCES\s+[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))(.*)/is', $rest, $m)) {
            $key = [
                'name' => $m[1],
                'type' => 'foreign',
                'columns' => static::extractColumns($m[2]),
                'ref_table' => $m[3],
                'ref_columns' => static::extractColumns($m[4]),
                'action' => 'add',
            ];
            $trail = trim($m[5]);
            if (preg_match('/ON\s+DELETE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $dm)) {
                $key['on_delete'] = strtoupper($dm[1]);
            }
            if (preg_match('/ON\s+UPDATE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $um)) {
                $key['on_update'] = strtoupper($um[1]);
            }
            $data['keys'][] = $key;
            return $data;
        }
        // ADD FOREIGN KEY (无 CONSTRAINT 名)
        if (preg_match('/^\s*ADD\s+FOREIGN\s+KEY\s*(\([^)]+\))\s*REFERENCES\s+[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))(.*)/is', $rest, $m)) {
            $key = [
                'name' => '',
                'type' => 'foreign',
                'columns' => static::extractColumns($m[1]),
                'ref_table' => $m[2],
                'ref_columns' => static::extractColumns($m[3]),
                'action' => 'add',
            ];
            $trail = trim($m[4]);
            if (preg_match('/ON\s+DELETE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $dm)) {
                $key['on_delete'] = strtoupper($dm[1]);
            }
            if (preg_match('/ON\s+UPDATE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $um)) {
                $key['on_update'] = strtoupper($um[1]);
            }
            $data['keys'][] = $key;
            return $data;
        }
        // ADD PRIMARY KEY
        if (preg_match('/^\s*ADD\s+PRIMARY\s+KEY\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => '',
                'type' => 'pk',
                'columns' => static::extractColumns($m[1]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD UNIQUE KEY/INDEX (有名)
        if (preg_match('/^\s*ADD\s+UNIQUE\s+(?:KEY|INDEX)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => $m[1],
                'type' => 'uk',
                'columns' => static::extractColumns($m[2]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD UNIQUE (无名，可选 KEY/INDEX 关键字)
        if (preg_match('/^\s*ADD\s+UNIQUE\s*(?:KEY|INDEX)?\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => '',
                'type' => 'uk',
                'columns' => static::extractColumns($m[1]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD FULLTEXT/SPATIAL KEY/INDEX (有名)
        if (preg_match('/^\s*ADD\s+(?:FULLTEXT|SPATIAL)\s+(?:KEY|INDEX)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => $m[1],
                'type' => 'index',
                'columns' => static::extractColumns($m[2]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD FULLTEXT/SPATIAL (无名，可选 KEY/INDEX 关键字)
        if (preg_match('/^\s*ADD\s+(?:FULLTEXT|SPATIAL)\s*(?:KEY|INDEX)?\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => '',
                'type' => 'index',
                'columns' => static::extractColumns($m[1]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD INDEX/KEY (有名)
        if (preg_match('/^\s*ADD\s+(?:INDEX|KEY)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => $m[1],
                'type' => 'index',
                'columns' => static::extractColumns($m[2]),
                'action' => 'add',
            ];
            return $data;
        }
        // ADD INDEX/KEY (无名)
        if (preg_match('/^\s*ADD\s+(?:INDEX|KEY)\s*(\([^)]+\))/is', $rest, $m)) {
            $data['keys'][] = [
                'name' => '',
                'type' => 'index',
                'columns' => static::extractColumns($m[1]),
                'action' => 'add',
            ];
            return $data;
        }

        // ---- DROP PRIMARY KEY ----
        if (preg_match('/^\s*DROP\s+PRIMARY\s+KEY/i', $rest)) {
            $data['keys'][] = [
                'name' => '',
                'type' => 'pk',
                'columns' => [],
                'action' => 'drop',
            ];
            return $data;
        }
        // ---- DROP INDEX/KEY ----
        if (preg_match('/^\s*DROP\s+(?:INDEX|KEY)\s+[`"\[]?(\w+)[`"\]]?/i', $rest, $m)) {
            $data['keys'][] = [
                'name' => $m[1],
                'type' => 'index',
                'columns' => [],
                'action' => 'drop',
            ];
            return $data;
        }
        // ---- DROP FOREIGN KEY ----
        if (preg_match('/^\s*DROP\s+FOREIGN\s+KEY\s+[`"\[]?(\w+)[`"\]]?/i', $rest, $m)) {
            $data['keys'][] = [
                'name' => $m[1],
                'type' => 'foreign',
                'columns' => [],
                'action' => 'drop',
            ];
            return $data;
        }

        // ======== 列操作（键操作全部返回后，这里才处理列） ========

        // ADD COLUMN — 必须带 COLUMN 关键字，或以反引号开头且非关键字
        if (preg_match('/^\s*ADD\s+COLUMN\s+[`"\[]?(\w+)[`"\]]?\s+(.+)/is', $rest, $m)) {
            $colName = $m[1];
            $typeDef = static::stripTrailingClauses($m[2]);
            $field = static::parseColumnDef('`' . $colName . '` ' . $typeDef);
            if ($field) {
                $field['action'] = 'add';
                $data['fields'][] = $field;
            }
        }
        // CHANGE COLUMN
        elseif (preg_match('/^\s*CHANGE\s+(?:COLUMN\s+)?[`"\[]?(\w+)[`"\]]?\s+[`"\[]?(\w+)[`"\]]?\s+(.+)/is', $rest, $m)) {
            $oldName = $m[1];
            $newName = $m[2];
            $typeDef = static::stripTrailingClauses($m[3]);
            $field = static::parseColumnDef('`' . $newName . '` ' . $typeDef);
            if ($field) {
                $field['action'] = 'change';
                $field['old_name'] = $oldName;
                $data['fields'][] = $field;
            }
        }
        // MODIFY COLUMN
        elseif (preg_match('/^\s*MODIFY\s+(?:COLUMN\s+)?[`"\[]?(\w+)[`"\]]?\s+(.+)/is', $rest, $m)) {
            $colName = $m[1];
            $typeDef = static::stripTrailingClauses($m[2]);
            $field = static::parseColumnDef('`' . $colName . '` ' . $typeDef);
            if ($field) {
                $field['action'] = 'modify';
                $data['fields'][] = $field;
            }
        }
        // DROP COLUMN — 必须是 DROP COLUMN（显式 COLUMN 关键字）才当列处理
        elseif (preg_match('/^\s*DROP\s+COLUMN\s+[`"\[]?(\w+)[`"\]]?/i', $rest, $m)) {
            $data['fields'][] = [
                'name' => $m[1],
                'action' => 'drop',
            ];
        }

        return $data;
    }

    /**
     * 解析 DROP TABLE
     *
     * 返回结构:
     * [
     *   'type'      => 'drop_table',
     *   'table'     => 'table_name',
     *   'if_exists' => false,
     * ]
     */
    protected static function parseDropTable($sql)
    {
        $ifExists = (bool) preg_match('/DROP\s+TABLE\s+IF\s+EXISTS/i', $sql);

        if (!preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"\[]?(\w+)[`"\]]?/i', $sql, $m)) {
            return ['type' => 'raw', 'sql' => $sql];
        }

        return [
            'type' => 'drop_table',
            'table' => $m[1],
            'if_exists' => $ifExists,
        ];
    }

    /**
     * 解析 UPDATE
     *
     * 返回结构:
     * [
     *   'type' => 'update',
     *   'table'=> 'table_name',
     *   'sql'  => '原始SQL',
     * ]
     */
    protected static function parseUpdate($sql)
    {
        $table = '';
        if (preg_match('/UPDATE\s+[`"\[]?(\w+)[`"\]]?/i', $sql, $m)) {
            $table = $m[1];
        }

        return [
            'type' => 'update',
            'table' => $table,
            'sql' => $sql,
        ];
    }

    // ===================================================================
    //  字段 / 键 解析器
    // ===================================================================

    /**
     * 解析单个列定义
     *
     * 输入: `col_name` int(10) unsigned NOT NULL DEFAULT '0' AUTO_INCREMENT COMMENT 'xxx'
     * 输出: ['name','type','length','default','nullable'=>0,'auto_inc'=>1,'unsigned'=>1,'comment'=>'xxx',...]
     *
     * @param  string $def
     * @return array|null
     */
    protected static function parseColumnDef($def)
    {
        // 1. 提取列名（支持反引号、双引号、方括号）
        if (!preg_match('/^\s*[`"\[]?(\w+)[`"\]]?\s+/', $def, $m)) {
            return null;
        }
        $name = $m[1];
        $remain = substr($def, strlen($m[0]));

        $field = [
            'name' => $name,
            'type' => '',
            'length' => '',
            'nullable' => 1,       // 1 = 可为 NULL（MySQL 默认），0 = NOT NULL
            'auto_inc' => 0,
            'unsigned' => 0,
            'comment' => '',
        ];

        // 2. 提取类型和长度: type[(length)]
        if (preg_match('/^(\w+)(?:\s*\(\s*([^)]*)\s*\))?/i', $remain, $tm)) {
            $field['type'] = strtolower($tm[1]);
            $field['length'] = $tm[2] ?? '';
            $remain = substr($remain, strlen($tm[0]));
        }

        $remain = trim($remain);

        // 3. UNSIGNED
        if (preg_match('/\bUNSIGNED\b/i', $remain)) {
            $field['unsigned'] = 1;
            $remain = preg_replace('/\bUNSIGNED\b/i', '', $remain, 1);
        }

        // 4. DEFAULT value — 必须在 NOT NULL 之前处理，因为 DEFAULT NULL 包含 NULL
        if (preg_match('/\bDEFAULT\s+(NULL|CURRENT_TIMESTAMP(?:\(\d+\))?|\'(?:\'\'|[^\'])*\'|\S+)/i', $remain, $dm)) {
            $dval = strtoupper($dm[1]);
            if ($dval === 'NULL') {
                $field['default'] = null;
            } else {
                $field['default'] = $dm[1];
            }
            $remain = preg_replace('/\bDEFAULT\s+' . preg_quote($dm[1], '/') . '/i', '', $remain, 1);
        }

        // 5. NOT NULL / NULL
        if (preg_match('/\bNOT\s+NULL\b/i', $remain)) {
            $field['nullable'] = 0;
            $remain = preg_replace('/\bNOT\s+NULL\b/i', '', $remain, 1);
        } elseif (preg_match('/\bNULL\b/i', $remain)) {
            $field['nullable'] = 1;
            $remain = preg_replace('/\bNULL\b/i', '', $remain, 1);
        }

        // 6. AUTO_INCREMENT
        if (preg_match('/\bAUTO_INCREMENT\b/i', $remain)) {
            $field['auto_inc'] = 1;
            $remain = preg_replace('/\bAUTO_INCREMENT\b/i', '', $remain, 1);
        }

        // 7. ON UPDATE CURRENT_TIMESTAMP（记录但不转换）
        if (preg_match('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d+\))?/i', $remain)) {
            $field['on_update_current_timestamp'] = 1;
            $remain = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d+\))?/i', '', $remain, 1);
        }

        // 8. COMMENT
        if (preg_match("/\bCOMMENT\s+'((?:[^'\\\\]|\\\\.)*)'/i", $remain, $cmm)) {
            $field['comment'] = str_replace("\\'", "'", $cmm[1]);
            $remain = preg_replace("/\bCOMMENT\s+'((?:[^'\\\\]|\\\\.)*)'/i", '', $remain, 1);
        }

        // 9. 清理剩余不兼容属性
        $remain = preg_replace('/\bCOLLATE\s+\w+/i', '', $remain);
        $remain = preg_replace('/\bCHARACTER\s+SET\s+\w+/i', '', $remain);
        $remain = preg_replace('/\bAFTER\s+[`"\[]?\w+[`"\]]?/i', '', $remain);
        $remain = preg_replace('/\bFIRST\b/i', '', $remain);
        $remain = preg_replace('/\bUSING\s+BTREE\b/i', '', $remain);
        $remain = preg_replace('/\bUSING\s+HASH\b/i', '', $remain);
        $remain = preg_replace('/\bZEROFILL\b/i', '', $remain);

        // 10. 强制 text 类字段 NOT NULL DEFAULT ''（MySQL 中 text 不允许非 NULL 默认值）
        if (static::isTextLikeType($field['type'])) {
            $field['nullable'] = 0;
            if (!array_key_exists('default', $field) || $field['default'] === null) {
                $field['default'] = "''";
            }
        }

        return $field;
    }

    /**
     * 解析键/约束定义（CREATE TABLE 内部）
     *
     * 输入示例:
     *   PRIMARY KEY (`id`)
     *   UNIQUE KEY `idx_name` (`col1`, `col2`)
     *   KEY `idx_name` (`col1`)
     *   INDEX `idx_name` (`col1`, `col2`) USING BTREE
     *   FULLTEXT KEY `idx` (`col1`)
     *   FOREIGN KEY (`col1`) REFERENCES `table2` (`id`) ON DELETE CASCADE
     *   CONSTRAINT `name` FOREIGN KEY ...
     *   CHECK (`score` >= 0)
     *
     * @param  string $def
     * @return array|null
     */
    protected static function parseKeyDef($def)
    {
        $def = trim($def);
        $def = preg_replace('/\s+USING\s+(BTREE|HASH)\b/i', '', $def);

        // PRIMARY KEY
        if (preg_match('/^\s*PRIMARY\s+KEY\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => '',
                'type' => 'pk',
                'columns' => static::extractColumns($m[1]),
            ];
        }

        // CONSTRAINT name PRIMARY KEY / UNIQUE / FOREIGN KEY / CHECK
        if (preg_match('/^\s*CONSTRAINT\s+[`"\[]?(\w+)[`"\]]?\s+(.+)/is', $def, $m)) {
            $constraintName = $m[1];
            $inner = trim($m[2]);

            if (preg_match('/^\s*PRIMARY\s+KEY\s*(\([^)]+\))/i', $inner, $pm)) {
                return [
                    'name' => $constraintName,
                    'type' => 'pk',
                    'columns' => static::extractColumns($pm[1]),
                ];
            }
            if (preg_match('/^\s*UNIQUE\s*(?:KEY|INDEX)?\s*(\([^)]+\))/i', $inner, $um)) {
                return [
                    'name' => $constraintName,
                    'type' => 'uk',
                    'columns' => static::extractColumns($um[1]),
                ];
            }
            if (preg_match('/^\s*FOREIGN\s+KEY\s*(\([^)]+\))\s*REFERENCES\s+[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))(.*)/is', $inner, $fm)) {
                $key = [
                    'name' => $constraintName,
                    'type' => 'foreign',
                    'columns' => static::extractColumns($fm[1]),
                    'ref_table' => $fm[2],
                    'ref_columns' => static::extractColumns($fm[3]),
                ];
                $trail = trim($fm[4]);
                if (preg_match('/ON\s+DELETE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $dm)) {
                    $key['on_delete'] = strtoupper($dm[1]);
                }
                if (preg_match('/ON\s+UPDATE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $um)) {
                    $key['on_update'] = strtoupper($um[1]);
                }
                return $key;
            }
            if (preg_match('/^\s*CHECK\s*\((.+)\)/is', $inner, $cm)) {
                return [
                    'name' => $constraintName,
                    'type' => 'check',
                    'expression' => trim($cm[1]),
                ];
            }
            return null;
        }

        // UNIQUE KEY/INDEX (有名)
        if (preg_match('/^\s*UNIQUE\s+(?:KEY|INDEX)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => $m[1],
                'type' => 'uk',
                'columns' => static::extractColumns($m[2]),
            ];
        }
        // UNIQUE (无名，可选 KEY/INDEX 关键字)
        if (preg_match('/^\s*UNIQUE\s*(?:KEY|INDEX)?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => '',
                'type' => 'uk',
                'columns' => static::extractColumns($m[1]),
            ];
        }

        // FULLTEXT KEY/INDEX
        if (preg_match('/^\s*FULLTEXT\s+(?:KEY|INDEX)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => $m[1],
                'type' => 'fulltext',
                'columns' => static::extractColumns($m[2]),
            ];
        }
        // FULLTEXT 无名（可选 KEY/INDEX 关键字）
        if (preg_match('/^\s*FULLTEXT\s*(?:KEY|INDEX)?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => '',
                'type' => 'fulltext',
                'columns' => static::extractColumns($m[1]),
            ];
        }

        // SPATIAL KEY/INDEX
        if (preg_match('/^\s*SPATIAL\s+(?:KEY|INDEX)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => $m[1],
                'type' => 'spatial',
                'columns' => static::extractColumns($m[2]),
            ];
        }
        // SPATIAL 无名（可选 KEY/INDEX 关键字）
        if (preg_match('/^\s*SPATIAL\s*(?:KEY|INDEX)?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => '',
                'type' => 'spatial',
                'columns' => static::extractColumns($m[1]),
            ];
        }

        // INDEX/KEY (有名)
        if (preg_match('/^\s*(?:INDEX|KEY)\s*[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => $m[1],
                'type' => 'index',
                'columns' => static::extractColumns($m[2]),
            ];
        }
        // INDEX/KEY (无名)
        if (preg_match('/^\s*(?:INDEX|KEY)\s*(\([^)]+\))/i', $def, $m)) {
            return [
                'name' => '',
                'type' => 'index',
                'columns' => static::extractColumns($m[1]),
            ];
        }

        // FOREIGN KEY
        if (preg_match('/^\s*FOREIGN\s+KEY\s*(\([^)]+\))\s*REFERENCES\s+[`"\[]?(\w+)[`"\]]?\s*(\([^)]+\))(.*)/is', $def, $m)) {
            $key = [
                'name' => '',
                'type' => 'foreign',
                'columns' => static::extractColumns($m[1]),
                'ref_table' => $m[2],
                'ref_columns' => static::extractColumns($m[3]),
            ];
            $trail = trim($m[4]);
            if (preg_match('/ON\s+DELETE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $dm)) {
                $key['on_delete'] = strtoupper($dm[1]);
            }
            if (preg_match('/ON\s+UPDATE\s+(RESTRICT|CASCADE|SET\s+NULL|NO\s+ACTION|SET\s+DEFAULT)/i', $trail, $um)) {
                $key['on_update'] = strtoupper($um[1]);
            }
            return $key;
        }

        // CHECK
        if (preg_match('/^\s*CHECK\s*\((.+)\)/is', $def, $m)) {
            return [
                'name' => '',
                'type' => 'check',
                'expression' => trim($m[1]),
            ];
        }

        return null;
    }

    // ===================================================================
    //  工具方法
    // ===================================================================

    /**
     * 构建 PostgreSQL 列类型字符串
     *
     * @param  array  $field
     * @return string  如 "integer", "varchar(255)", "numeric(10,2)"
     */
    protected static function buildPgColumnType(array $field)
    {
        $mysqlType = $field['type'] ?? '';
        $length = $field['length'] ?? '';
        $autoInc = !empty($field['auto_inc']);

        // ENUM/SET → varchar(255) / text
        if ($mysqlType === 'enum') {
            return 'varchar(255)';
        }
        if ($mysqlType === 'set') {
            return 'text';
        }

        // 查映射表
        $pgType = static::$typeMap[$mysqlType] ?? $mysqlType;

        // 自增列 → SERIAL / BIGSERIAL / SMALLSERIAL (PG 中 SERIAL 已自带 NOT NULL)
        if ($autoInc) {
            switch ($mysqlType) {
                case 'bigint':
                    return 'bigserial';
                case 'smallint':
                case 'tinyint':
                    return 'smallserial';
                default:
                    return 'serial';
            }
        }

        // 只有少数类型支持长度参数
        if ($length !== '' && $length !== null && in_array($mysqlType, static::$typesWithLength)) {
            return $pgType . '(' . $length . ')';
        }

        return $pgType;
    }

    /**
     * 从括号包围的列名列表中提取列名数组
     *
     * 输入: (`col1`, `col2`, `col3`)
     * 输出: ['col1', 'col2', 'col3']
     *
     * @param  string   $parens
     * @return string[]
     */
    protected static function extractColumns($parens)
    {
        $parens = trim($parens);
        $parens = trim($parens, '()');
        $cols = [];
        foreach (explode(',', $parens) as $col) {
            $col = trim($col);
            $col = trim($col, '`"[] ');
            if ($col !== '') {
                $cols[] = $col;
            }
        }
        return $cols;
    }

    /**
     * 查找匹配的闭括号（跳过字符串字面量）
     *
     * @param  string    $str
     * @param  int       $openPos 开括号位置
     * @return int|false 闭括号位置，或 false
     */
    protected static function findMatchingParen($str, $openPos)
    {
        $depth = 1;
        $i = $openPos + 1;
        $len = strlen($str);

        while ($i < $len && $depth > 0) {
            $ch = $str[$i];
            $next = ($i + 1 < $len) ? $str[$i + 1] : null;

            // 处理字符串字面量
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++;
                while ($i < $len) {
                    if ($str[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($str[$i] === $quote) {
                        if ($i + 1 < $len && $str[$i + 1] === $quote) {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }

            if ($depth > 0) {
                $i++;
            }
        }

        return $depth === 0 ? $i : false;
    }

    /**
     * 按逗号分割字符串，正确处理括号嵌套和字符串字面量
     *
     * @param  string   $str
     * @return string[]
     */
    protected static function splitByComma($str)
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $ch = $str[$i];
            $next = ($i + 1 < $len) ? $str[$i + 1] : null;

            // 处理字符串字面量
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    if ($str[$i] === '\\') {
                        $current .= $str[$i];
                        $i++;
                        if ($i < $len) {
                            $current .= $str[$i];
                            $i++;
                        }
                        continue;
                    }
                    if ($str[$i] === $quote) {
                        if ($i + 1 < $len && $str[$i + 1] === $quote) {
                            $current .= $str[$i] . $str[$i + 1];
                            $i += 2;
                            continue;
                        }
                        $current .= $str[$i];
                        $i++;
                        break;
                    }
                    $current .= $str[$i];
                    $i++;
                }
                $i--; // 外层循环会 +1
                continue;
            }

            if ($ch === '(') {
                $depth++;
                $current .= $ch;
            } elseif ($ch === ')') {
                $depth--;
                $current .= $ch;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $ch;
            }
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * 清理列定义末尾的 MySQL 特有子句（AFTER, FIRST）
     *
     * @param  string $def
     * @return string
     */
    protected static function stripTrailingClauses($def)
    {
        $def = preg_replace('/\s+AFTER\s+[`"\[]?\w+[`"\]]?/i', '', $def);
        $def = preg_replace('/\s+FIRST\b/i', '', $def);
        return trim($def);
    }

    /**
     * 转义 PostgreSQL 字符串字面量（单引号翻倍）
     *
     * @param  string $str
     * @return string 已加引号包围
     */
    protected static function quotePgString($str)
    {
        return "'" . str_replace("'", "''", $str) . "'";
    }

    /**
     * 判断是否为 text 类类型（MySQL 中这些类型不允许非 NULL 默认值）
     *
     * @param  string $type
     * @return bool
     */
    protected static function isTextLikeType($type)
    {
        return in_array(strtolower($type), ['tinytext', 'mediumtext', 'longtext', 'text', 'json', 'jsonb']);
    }

    /**
     * 构建 PostgreSQL DEFAULT 值
     *
     * MySQL 中 text 类型不允许非 NULL 默认值，转为 PG 时用空字符串。
     *
     * @param  array       $field
     * @return string|null 返回 DEFAULT 后的 SQL 片段，或 null 表示不需要 DEFAULT
     */
    protected static function buildDefaultValue(array $field)
    {
        if (!array_key_exists('default', $field)) {
            return null;
        }

        // DEFAULT NULL：text 类类型转为 ''，其他类型跳过（PG 默认为 NULL）
        if ($field['default'] === null) {
            if (static::isTextLikeType($field['type'])) {
                return "''";
            }
            return null;
        }

        // DEFAULT 'string' / DEFAULT 123 / DEFAULT CURRENT_TIMESTAMP
        return (string) $field['default'];
    }
}
