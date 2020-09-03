<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.09.20 22:00:14
 */

declare(strict_types = 1);
namespace dicr\exec;

use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Ошибка выполнения команды.
 */
class ExecException extends Exception
{
    /** @var string выполняемая команда */
    protected $cmd;

    /**
     * {@inheritDoc}
     *
     * @param string $cmd команда
     * @param string $error ошибка
     * @param int $code код ошибки
     * @param ?Throwable $prev предыдущая проблема
     */
    public function __construct(string $cmd, string $error = '', int $code = 0, ?Throwable $prev = null)
    {
        if ($cmd === '') {
            throw new InvalidArgumentException('cmd');
        }

        $this->cmd = $cmd;

        if ($error === '') {
            $last = error_get_last();
            if (! empty($last['message'])) {
                $error = $last['message'];
                error_clear_last();
            } else {
                $error = 'ошибка запуска команды';
            }
        }

        parent::__construct($error, $code, $prev);
    }

    /**
     * Возвращает команду
     *
     * @return string
     */
    public function getCmd() : string
    {
        return $this->cmd;
    }
}
