<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.09.20 21:59:12
 */

declare(strict_types = 1);
namespace dicr\exec;

/**
 * Исполнитель команд.
 */
interface ExecInterface
{
    /**
     * Выполняет внешнюю команду.
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $options опции функции
     * - bool $escape - выполнить экранирование аргументов, false
     * @return string вывод команды
     * @throws ExecException
     */
    public function run(string $cmd, array $args = [], array $options = []) : string;
}
