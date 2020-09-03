<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 04.09.20 02:13:39
 */

declare(strict_types = 1);
namespace dicr\exec;

use InvalidArgumentException;
use function array_filter;
use function function_exists;
use function in_array;
use function ob_get_clean;

/**
 * Выполнение команд локально.
 * Использует различные доступные методы.
 */
class LocalExec implements ExecInterface
{
    /**
     * Возвращает список запрещенных функций.
     *
     * @return string[]
     */
    private static function disabledFuncs() : array
    {
        /** @var array запрещенные функции */
        static $fns;

        if (! isset($fns)) {
            $disabledList = ini_get('disable_functions') . ',' . ini_get('suhosin.executor.func.blacklist');
            $fns = preg_split('~[\s\,]+~um', $disabledList, - 1, PREG_SPLIT_NO_EMPTY);
        }

        return $fns;
    }

    /**
     * Проверяет запрещена ли функция.
     *
     * @param string $func название функции
     * @return bool
     */
    public static function isDisabled(string $func) : bool
    {
        return ! function_exists($func) || in_array($func, self::disabledFuncs());
    }

    /**
     * Создает команду для запуска
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string
     */
    public static function createCommand(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = trim($cmd);
        if ($cmd === '') {
            throw new InvalidArgumentException('empty cmd');
        }

        $command = escapeshellcmd($cmd);

        if (! empty($args)) {
            $args = array_filter($args, static function($val) {
                return $val !== null;
            });

            if (! isset($opts['escape']) || $opts['escape']) {
                $args = array_map(static function($arg) {
                    return escapeshellarg($arg);
                }, $args);
            }

            $command .= ' ' . implode(' ', $args);
        }

        return $command;
    }

    /**
     * Выполняет exec
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     *        - escape bool экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     */
    public static function exec(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        /** @var int $return */
        $return = null;

        /** @var array $out */
        $out = [];

        exec($cmd, $out, $return);
        $out = implode('', $out);

        if (! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }

        return $out;
    }

    /**
     * Выполняет passthru
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     */
    public static function passthru(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        /** @var int $return */
        $return = null;

        ob_start();
        passthru($cmd, $return);
        $out = ob_get_clean();

        if (! empty($return)) {
            throw new ExecException($cmd, $out, $return);
        }

        return $out;
    }

    /**
     * Выполняет system
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     */
    public static function system(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        $ret = shell_exec($cmd);
        if ($ret === null) {
            throw new ExecException($cmd);
        }

        return $ret;
    }

    /**
     * Выполняет shell_exec
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     */
    public static function shellExec(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        $out = shell_exec($cmd);
        if ($out === null) {
            throw new ExecException($cmd);
        }

        return $out;
    }

    /**
     * Выполняет proc_open
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public static function popen(string $cmd, array $args = [], array $opts = []) : string
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        $f = @popen($cmd, 'rt');
        if (! $f) {
            throw new ExecException($cmd);
        }

        $out = @stream_get_contents($f);
        if ($out === false) {
            throw new ExecException($cmd);
        }

        if (@pclose($f) === - 1) {
            throw new ExecException($cmd);
        }

        return $out;
    }

    /**
     * Выполняет proc_open
     *
     * @param string $cmd команда
     * @param array $args аргументы
     * @param array $opts опции
     * - bool $escape экранировать аргументы
     * @return string вывод команды
     * @throws ExecException
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public static function procOpen(string $cmd, array $args = [], array $opts = [])
    {
        $cmd = self::createCommand($cmd, $args, $opts);

        $pipes = [];

        $proc = @proc_open($cmd, [
            0 => ['file', '/dev/null'],
            1 => ['pipe', 'r'],
            2 => ['pipe', 'r']
        ], $pipes);

        if ($proc === false) {
            throw new ExecException($cmd);
        }

        $out = @stream_get_contents($pipes[1]);
        @fclose($pipes[1]);

        $out .= @stream_get_contents($pipes[2]);
        @fclose($pipes[2]);

        $ret = @proc_close($proc);
        if (! empty($ret)) {
            throw new ExecException($cmd, $out, $ret);
        }

        return $out;
    }

    /**
     * @inheritdoc
     */
    public function run(string $cmd, array $args = [], array $opts = []) : string
    {
        $out = null;

        if (! self::isDisabled('exec')) {
            $out = self::exec($cmd, $args, $opts);
        } elseif (! self::isDisabled('system')) {
            $out = self::system($cmd, $args, $opts);
        } elseif (! self::isDisabled('popen')) {
            $out = self::popen($cmd, $args, $opts);
        } elseif (! self::isDisabled('proc_open')) {
            $out = self::procOpen($cmd, $args, $opts);
        } elseif (! self::isDisabled('passthru')) {
            $out = self::passthru($cmd, $args, $opts);
        } elseif (! self::isDisabled('shell_exec')) {
            $out = self::shellExec($cmd, $args, $opts);
        } /** @noinspection InvertedIfElseConstructsInspection */ else {
            throw new ExecException(self::createCommand($cmd, $args, $opts), 'Все функции запрещены');
        }

        return $out;
    }
}
