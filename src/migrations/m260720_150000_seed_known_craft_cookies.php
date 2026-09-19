<?php

namespace dowleydeveloped\cookieconsent\migrations;

use craft\db\Migration;
use dowleydeveloped\cookieconsent\services\KnownCookies;

class m260720_150000_seed_known_craft_cookies extends Migration
{
    public function safeUp(): bool
    {
        KnownCookies::seedCraftDefaults();
        return true;
    }

    public function safeDown(): bool
    {
        // Defaults may have been edited or deliberately retained, so an update
        // rollback must not delete user-managed cookie elements.
        return true;
    }
}
