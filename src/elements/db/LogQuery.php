<?php

namespace dowleydeveloped\cookieconsent\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

class LogQuery extends ElementQuery
{
    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable("dowley_cookies_tracked");

        $this->query->select([
            "dowley_cookies_tracked.accepted",
            "dowley_cookies_tracked.rejected",
        ]);

        return parent::beforePrepare();
    }

}
