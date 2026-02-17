<?php
namespace App;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

class ColoredFormatter extends LineFormatter {
    // Codes ANSI pour les couleurs
    private const COLORS = [
        Logger::DEBUG     => "\033[36m",      // Cyan
        Logger::INFO      => "\033[32m",      // Vert
        Logger::WARNING   => "\033[33m",      // Jaune
        Logger::ERROR     => "\033[31m",      // Rouge
        Logger::CRITICAL  => "\033[35m",      // Magenta
    ];

    private const RESET = "\033[0m";

    public function format(LogRecord $record): string {
        $level = $record->level->value;
        $color = self::COLORS[$level] ?? self::RESET;
        
        // Appeler le parent pour formater
        $formatted = parent::format($record);
        
        // Remplacer le niveau par la version colorée
        return str_replace(
            $record->level->name,
            $color . $record->level->name . self::RESET,
            $formatted
        );
    }
}

class Log {
    public static function getLogger($name, $filename = null): Logger {
        
        $logFile = $filename ?? $name;
        
        $logPath = __DIR__ . "/../../logs/$logFile.log";

        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }

        $logger = new Logger($name);
        
        // Créer le handler avec formatage coloré
        $handler = new StreamHandler($logPath, Logger::DEBUG);
        
        // Ajouter le formatter personnalisé avec couleurs
        $formatter = new ColoredFormatter(
            "[%datetime%] %level_name%: %message% %context%\n",
            'd-m-Y H:i:s'
        );
        $handler->setFormatter($formatter);
        
        $logger->pushHandler($handler);

        return $logger;
    }

    public static function error($message, $filename = 'error'): void {
        $logger = self::getLogger('error', $filename);
        $logger->error($message);
    }

    public static function warning($message, $filename = 'warning'): void {
        $logger = self::getLogger('warning', $filename);
        $logger->warning($message);
    }

    public static function info($message, $filename = 'info'): void {
        $logger = self::getLogger('info', $filename);
        $logger->info($message);
    }

    public static function debug($message, $filename = 'debug'): void {
        $logger = self::getLogger('debug', $filename);
        $logger->debug($message);
    }

    public static function critical($message, $filename = 'critical'): void {
        $logger = self::getLogger('critical', $filename);
        $logger->critical($message);
    }
}
