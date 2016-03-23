<?php

use Eloquent\Cache\Query\SquirrelQueryBuilder;
use Eloquent\Cache\Query\SquirrelQuery;
use Eloquent\Cache\Query\SquirrelQueryWhere;

class SquirrelQueryTests extends PHPUnit_Framework_TestCase
{
    /**
     *
     */
    public function testSquirrelQueryBasicFind()
    {
        // Test simple case, User::find(1), no softdeletes
        $whereData = [
            [
                'type' => 'Basic',
                'column' => 'users.id',
                'operator' => '=',
                'value' => 1,
                'boolean' => 'and'
            ]
        ];
        
        $deletedAtColumnName = null;

        $query = new SquirrelQuery( $whereData, $deletedAtColumnName );

        $this->assertCount(1, $query->wheres);
        $this->assertEquals( "id", $query->uniqueKeyString() );
        
        $cacheKeys = $query->cacheKeys();
        $this->assertCount(1, $cacheKeys);
        $this->assertEquals( 'a:1:{s:2:"id";s:1:"1";}', $cacheKeys[0] );
        
        $cacheKeys = $query->cacheKeys("Squirrel::App\\User::");
        $this->assertEquals( 'Squirrel::App\User::a:1:{s:2:"id";s:1:"1";}', $cacheKeys[0] );

        $keysWithValues = $query->keysWithValues();
        $this->assertCount(1, $keysWithValues);
        $this->assertArrayHasKey( 'id', $keysWithValues );
        $this->assertEquals( 1, $keysWithValues['id'] );

        $this->assertEmpty( $query->deletedAtObject() );

        $fields = $query->allExcludingDeletedAt();
        $this->assertCount(1, $fields);

        $this->assertEquals( "Basic", $fields[0]->type );
        $this->assertEquals( "id", $fields[0]->column );
        $this->assertEquals( "=", $fields[0]->operator );
        $this->assertEquals( "1", $fields[0]->value );
        $this->assertEquals( "and", $fields[0]->boolean );

        $first = $query->firstWithColumnName("deleted_at");
        $this->assertEmpty($first);

        $first = $query->firstWithColumnName("id");
        $this->assertEquals( "id", $first->column );
        $this->assertEquals( "=", $first->operator );
        $this->assertEquals( "1", $first->value );

        $this->assertEmpty( $query->inStatement() );

        $this->assertTrue($query->isCacheable());
    }

    public function testSquirrelQueryBasicFindWithSoftDeletes()
    {
        // Test simple case, User::find(1), no softdeletes
        $whereData = [
            [
                'type' => 'Null',
                'column' => 'users.deleted_at',
                'boolean' => 'and'
            ],
            [
                'type' => 'Basic',
                'column' => 'users.id',
                'operator' => '=',
                'value' => 1,
                'boolean' => 'and'
            ]
        ];
        
        $deletedAtColumnName = 'deleted_at';

        $query = new SquirrelQuery( $whereData, $deletedAtColumnName );

        $this->assertCount(2, $query->wheres);
        $this->assertEquals( "id", $query->uniqueKeyString() );
        
        $cacheKeys = $query->cacheKeys();
        $this->assertCount(1, $cacheKeys);
        $this->assertEquals( 'a:1:{s:2:"id";s:1:"1";}', $cacheKeys[0] );
        
        $cacheKeys = $query->cacheKeys("Squirrel::App\\User::");
        $this->assertEquals( 'Squirrel::App\User::a:1:{s:2:"id";s:1:"1";}', $cacheKeys[0] );

        $keysWithValues = $query->keysWithValues();
        $this->assertCount(1, $keysWithValues);
        $this->assertArrayHasKey( 'id', $keysWithValues );
        $this->assertEquals( 1, $keysWithValues['id'] );

        $deletedAtObject = $query->deletedAtObject();
        $this->assertNotEmpty( $deletedAtObject );
        $this->assertEquals( "and", $deletedAtObject->boolean );
        $this->assertEquals( "deleted_at", $deletedAtObject->column );
        $this->assertEquals( "Is", $deletedAtObject->operator );
        $this->assertEquals( "Null", $deletedAtObject->value );
        $this->assertEquals( "Null", $deletedAtObject->type );

        $fields = $query->allExcludingDeletedAt();
        $this->assertCount(1, $fields);

        $this->assertEquals( "and", $fields[0]->boolean );
        $this->assertEquals( "id", $fields[0]->column );
        $this->assertEquals( "=", $fields[0]->operator );
        $this->assertEquals( "1", $fields[0]->value );
        $this->assertEquals( "Basic", $fields[0]->type );

        $first = $query->firstWithColumnName("deleted_at");
        $this->assertNotEmpty($first);
        $this->assertEquals( "deleted_at", $first->column );
        $this->assertEquals( "Is", $first->operator );
        $this->assertEquals( "Null", $first->value );

        $first = $query->firstWithColumnName("id");
        $this->assertEquals( "id", $first->column );
        $this->assertEquals( "=", $first->operator );
        $this->assertEquals( "1", $first->value );

        $this->assertEmpty( $query->inStatement() );

        $this->assertTrue($query->isCacheable());
    }

    
}
