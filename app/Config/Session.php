<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\DatabaseHandler;

class Session extends BaseConfig
{
    public string $driver = DatabaseHandler::class;

    public string $cookieName = 'qsi_ams_session';

    public int $expiration = 7200;

    public string $savePath = 'ci_sessions';

    public bool $matchIP = false;

    public int $timeToUpdate = 300;

    public bool $regenerateDestroy = true;

    public ?string $DBGroup = null;

    public string $sidRegexp = '[0-9a-v]{32}';
}
