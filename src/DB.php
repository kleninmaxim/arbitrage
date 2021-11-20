<?php

namespace src;

class DB
{
    private static $connect;

    public static function connect()
    {

        try {

            $db = new \PDO(
                'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB . '',
                MYSQL_USER,
                MYSQL_PASSWORD,
                [\PDO::ATTR_PERSISTENT => true]
            );

            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {

            print 'Error!: ' . $e->getMessage() . '<br/>';

            die();

        }

        self::$connect = $db;

    }

    public static function selectOrderbook($pair)
    {

        $sth = self::$connect->prepare(
            "SELECT * FROM `orderbooks` WHERE `pair` = :pair AND `exchange` = :exchange LIMIT 1"
        );

        $sth->execute(['pair' => $pair, 'exchange' => EXCHANGE]);

        return $sth->fetch(\PDO::FETCH_ASSOC);

    }

    public static function insertTheoreticalOrders(
        $order_id_taker_one,
        $order_id_taker_two,
        $order_id_taker_three,
        $triangles,
        $profit_percentage,
        $result,
        $profit
    )
    {

        $exchange = EXCHANGE;

        $sth = self::$connect->prepare("
            INSERT INTO `theoretical_orders` (
                                              `exchange`,
                                              `order_id_taker_one`, 
                                              `order_id_taker_two`, 
                                              `order_id_taker_three`, 
                                              `triangles`, 
                                              `profit_percentage`, 
                                              `result`, 
                                              `profit`
                                             ) VALUES (
                                              '{$exchange}',
                                              '{$order_id_taker_one}', 
                                              '{$order_id_taker_two}', 
                                              '{$order_id_taker_three}', 
                                              '{$triangles}', 
                                              '{$profit_percentage}', 
                                              '{$result}', 
                                              '{$profit}'
                                             )
        ");

        $sth->execute();

    }

    public static function insertError($name, $message)
    {

        $exchange = EXCHANGE;

        $sth = self::$connect->prepare("
            INSERT INTO `errors` (`exchange`, `name`, `message`) VALUES ('{$exchange}', '{$name}', '{$message}')
        ");

        $sth->execute();

    }

    public static function updateOrderBook($orderbook, $pair)
    {

        $sth = self::$connect->prepare("
            UPDATE `orderbooks` SET `orderbook` = :orderbook, `updated_at` = :updated_at WHERE `pair` = :pair
        ");

        $sth->execute([
            'pair' => $pair,
            'orderbook' => json_encode($orderbook),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

    }

    public static function createTables()
    {

        self::createTableOrderBooks();

        self::createTheoreticalOrders();

        self::createTableErrors();

        foreach (Cache::getPairs() as $pair) self::insertOrderBooks($pair);

    }

    private static function insertOrderBooks($pair)
    {

        $exchange = EXCHANGE;

        $sth = self::$connect->prepare("
            INSERT IGNORE INTO `orderbooks` (`exchange`, `pair`) VALUES ('{$exchange}', '{$pair}')
        ");

        $sth->execute();

    }

    private static function createTableErrors()
    {

        $sth = self::$connect->prepare("
            CREATE TABLE IF NOT EXISTS `errors` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `exchange` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `message` JSON NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `id_UNIQUE` (`id` ASC)
            );
        ");

        $sth->execute();

    }

    private static function createTableOrderBooks()
    {

        $sth = self::$connect->prepare("
            CREATE TABLE IF NOT EXISTS `orderbooks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `exchange` VARCHAR(255) NOT NULL,
                `pair` VARCHAR(45) NOT NULL,
                `orderbook` JSON NULL,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `id_UNIQUE` (`id` ASC),
                UNIQUE INDEX `pair_UNIQUE` (`pair` ASC)
            );
        ");

        $sth->execute();

    }

    private static function createTheoreticalOrders()
    {

        $sth = self::$connect->prepare("
            CREATE TABLE IF NOT EXISTS `theoretical_orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `exchange` VARCHAR(255) NOT NULL,
                `order_id_taker_one` VARCHAR(255) NOT NULL,
                `order_id_taker_two` VARCHAR(255) NOT NULL,
                `order_id_taker_three` VARCHAR(255) NOT NULL,
                `triangles` VARCHAR(45) NOT NULL,
                `profit_percentage` DECIMAL(25,8) NOT NULL,
                `result` JSON NOT NULL,
                `profit` JSON NOT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `id_UNIQUE` (`id` ASC)
            );
        ");

        $sth->execute();

    }

}