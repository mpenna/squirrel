<?php
date_default_timezone_set("America/Los_Angeles");

use Eloquent\Cache\Squirrel;
use Eloquent\Cache\SquirrelCache;

class ModifiedTestUser extends \Illuminate\Database\Eloquent\Model
{
    use Squirrel;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $int1 = mt_rand(1262055681, 1262055681);
        $int2 = mt_rand(1262055681, 1262055681);

        $attributes = [
            "id"         => 1,
            "uuid"       => "b7b88005-c4e7-47f6-9303-c497358d76bf",
            "first_name" => "David",
            "last_name"  => "Peace",
            "account_id" => 27,
            "email"      => "foo@bar.com",
            "password"   => "suumefRespurAbraF8aten7Huswathed",
            "created_at" => date("Y-m-d H:i:s", $int1),
            "updated_at" => date("Y-m-d H:i:s", $int2),
            "deleted_at" => null
        ];
        
        $this->setRawAttributes($attributes);
    }

    // Add more keys
    public function getUniqueKeys()
    {
        $primaryKey = $this->getKeyName();
        return [$primaryKey, "uuid", ["account_id", "email"]];
    }

    // Change to not active
    protected function isCacheActive()
    {
        return false;
    }

    // Change to 7 days
    public function cacheExpirationMinutes()
    {
        return (60 * 24) * 7;
    }
}