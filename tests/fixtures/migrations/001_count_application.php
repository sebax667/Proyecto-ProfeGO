<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec('UPDATE migration_marker SET application_count = application_count + 1');
};
