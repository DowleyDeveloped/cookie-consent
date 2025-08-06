<?php

namespace dowleydeveloped\cookieconsent\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\EntryType;
use yii\db\ActiveQueryInterface;

class CookieRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%dowley_cookies_enabled}}';
    }

    /**
     * Returns the content template's element.
     *
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the content template's entry type.
     *
     * @return ActiveQueryInterface
     */
    public function getType(): ActiveQueryInterface
    {
        return $this->hasOne(EntryType::class, ['id' => 'type']);
    }
}
