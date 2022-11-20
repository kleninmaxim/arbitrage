<?php

namespace Src\Databases;

use PDO;
use PDOStatement;
use Src\Databases\MySqlTables\BalancesTable;
use Src\Databases\MySqlTables\MirrorTrades;
use Src\Databases\MySqlTables\OrdersTable;
use Src\Databases\MySqlTables\Trades;

/**
 * @method static mixed get(array $execute_array = null)
 * @method static mixed getAll(array $execute_array = null)
 * @method static mixed column(array $execute_array = null)
 */
class MySql
{
    use BalancesTable, OrdersTable, Trades, MirrorTrades;

    public PDO $connection;
    private string $query = '';
    private ?array $execute_array = [];

    public function __construct(array $config)
    {
        $this->connection = new PDO(
            'mysql:' . http_build_query($config, '', ';'),
            options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    public static function init(...$parameters): static
    {
        return new static(...$parameters);
    }

    public function query(string $query): static
    {
        $this->query = $query;
        return $this;
    }

    public function insert(string $table, array $columns_values): static
    {
        $columns = array_keys($columns_values);

        $this->query = sprintf(
        /** @lang sql */ 'INSERT INTO `%s` (`%s`) VALUES (:%s)',
            $table,
            implode('`, `', $columns),
            implode(', :', $columns)
        );

        $this->execute_array = $columns_values;

        return $this;
    }

    public function update(string $table, array $column_values): static
    {
        $last_element_key = array_key_last($column_values);

        $sql = '';
        foreach ($column_values as $column => $value)
            $sql .= sprintf(
                '`%s` = :%s',
                $column,
                ($column != $last_element_key ? $column . ', ' : $column)
            );

        $this->query = sprintf(
        /** @lang sql */ 'UPDATE `%s` SET %s',
            $table,
            $sql
        );

        $this->execute_array = $column_values;

        return $this;
    }

    public function insertOrUpdate(string $table, array $insert_columns_values, array $update_columns): static
    {
        $this->insert($table, $insert_columns_values);

        $get_for_last = $update_columns;

        $last_element = array_pop($get_for_last);

        $sql = '';
        foreach ($update_columns as $column)
            $sql .= sprintf(
                '`%s` = :%s',
                $column,
                ($column != $last_element ? $column . ', ' : $column)
            );

        $this->query .= ' ON DUPLICATE KEY ' . sprintf(/** @lang sql */ 'UPDATE %s', $sql);

        $this->execute_array = $insert_columns_values;

        return $this;
    }

    public function replace(string $table, array $columns_values): static
    {
        $columns = array_keys($columns_values);

        $this->query = sprintf(
        /** @lang sql */ 'REPLACE INTO `%s` (`%s`) VALUES (:%s)',
            $table,
            implode('`, `', $columns),
            implode(', :', $columns)
        );

        $this->execute_array = $columns_values;

        return $this;
    }

    public function select(string $table, array $columns = []): static
    {
        $this->query = sprintf(
        /** @lang sql */ 'SELECT %s FROM `%s`',
            ($columns ? '`' . implode('`, `', $columns) . '`' : '*'),
            $table
        );

        return $this;
    }

    public function where(array ...$conditions): static
    {
        $sql = '';

        $last_element_key = array_key_last($conditions);

        foreach ($conditions as $key => $condition) {

            $sql .= sprintf(
                '`%s` %s :%s%s',
                $condition[0],
                $condition[1],
                $condition[0],
                ($key != $last_element_key ? ' AND ' : '')
            );

            $this->execute_array[$condition[0]] = $condition[2];

        }

        $this->query .= ' WHERE ' . $sql;

        return $this;
    }

    public function ignore(): static
    {
        $this->query = str_replace('INSERT', 'INSERT IGNORE', $this->query);

        return $this;
    }

    public function getLastInsertId(): static
    {
        $this->query = /** @lang sql */ 'SELECT LAST_INSERT_ID() AS last_id';
        return $this;
    }

    public function getParentId(string $table, string $column, string $value): int
    {
        return $this->select($table, ['id'])->where([$column, '=', $value])->get()['id'];
    }

    public function execute(array $execute_array = null): void
    {
        $this->sth($execute_array);
    }

    public function __call($method, $args): mixed
    {
        return match($method) {
            'get' => ($this->sth($args))->fetch(),
            'getAll' => ($this->sth($args))->fetchAll(),
            'column' => ($this->sth($args))->fetchAll(PDO::FETCH_COLUMN)
        };
    }

    private function sth(array $execute_array = null): bool|PDOStatement
    {
        $sth = $this->connection->prepare($this->query . ';');

        $sth->execute($execute_array ?: $this->execute_array);

        [$this->execute_array, $this->query] = [[], ''];

        return $sth;
    }
}