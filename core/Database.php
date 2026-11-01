<?php
class Database
{
    private static ?mysqli $connectionInstance = null;
    private static string $dbHost = 'localhost';
    private static string $dbUser = 'root';
    private static string $dbPassword = '';
    private static string $dbName = 'morfem';

    public static function getConnection(): mysqli
    {
        if (self::$connectionInstance === null) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $activeConnection = @mysqli_connect(self::$dbHost, self::$dbUser, self::$dbPassword, self::$dbName);

            if (!$activeConnection) {
                die('<div style="font-family:monospace;padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;margin:20px auto;max-width:600px;">
                    <strong>Database Connection Error:</strong> Pastikan MySQL aktif di XAMPP dan database <code>stki</code> sudah di-import.<br>
                    Detail: ' . htmlspecialchars(mysqli_connect_error()) . '
                </div>');
            }

            mysqli_set_charset($activeConnection, 'utf8mb4');
            self::$connectionInstance = $activeConnection;
        }

        return self::$connectionInstance;
    }
}
