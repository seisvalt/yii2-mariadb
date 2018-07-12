<?php
declare(strict_types=1);

use yii\base\DynamicModel;
use yii\db\Expression;
use yii\db\JsonExpression;
use yii\db\Query;
use yii\db\Schema;
use yii\helpers\Json;

class QueryBuilderTest extends \yiiunit\framework\db\mysql\QueryBuilderTest
{
    protected function getQueryBuilder($reset = true, $open = false)
    {
        $connection = $this->getConnection($reset, $open);
        \Yii::$container->set('db', $connection);
        return $connection->getQueryBuilder();
    }


    /**
     * This is not used as a dataprovider for testGetColumnType to speed up the test
     * when used as dataprovider every single line will cause a reconnect with the database which is not needed here.
     */
    public function columnTypes()
    {
        return \array_merge(array_filter(parent::columnTypes(), function($elem) {
            if ($elem[0] === Schema::TYPE_JSON) {
                return false;
            }
            return true;
        }),
            [
                Schema::TYPE_JSON,
                $this->json(),
                "json",
                "json CHECK (JSON_VALID([[json]]))"
            ]
        );
    }

    public function checksProvider(): void
    {
        $this->markTestIncomplete('Adding/dropping check constraints IS supported in MariaDB.');
    }

    public function defaultValuesProvider(): void
    {
        $this->markTestIncomplete('Adding/dropping default constraints IS not supported in MariaDB.');
    }

    public function conditionProvider()
    {
        return \array_merge(parent::conditionProvider(), [
            // json conditions
            [
                ['=', 'jsoncol', new JsonExpression(['lang' => 'uk', 'country' => 'UA'])],
                '[[jsoncol]] = :qp0', [':qp0' => '{"lang":"uk","country":"UA"}'],
            ],
            [
                ['=', 'jsoncol', new JsonExpression([false])],
                '[[jsoncol]] = :qp0', [':qp0' => '[false]']
            ],
            'object with type. Type is ignored for MySQL' => [
                ['=', 'prices', new JsonExpression(['seeds' => 15, 'apples' => 25], 'jsonb')],
                '[[prices]] = :qp0', [':qp0' => '{"seeds":15,"apples":25}'],
            ],
            'nested json' => [
                ['=', 'data', new JsonExpression(['user' => ['login' => 'silverfire', 'password' => 'c4ny0ur34d17?'], 'props' => ['mood' => 'good']])],
                '[[data]] = :qp0', [':qp0' => '{"user":{"login":"silverfire","password":"c4ny0ur34d17?"},"props":{"mood":"good"}}']
            ],
            'null value' => [
                ['=', 'jsoncol', new JsonExpression(null)],
                '[[jsoncol]] = :qp0', [':qp0' => 'null']
            ],
            'null as array value' => [
                ['=', 'jsoncol', new JsonExpression([null])],
                '[[jsoncol]] = :qp0', [':qp0' => '[null]']
            ],
            'null as object value' => [
                ['=', 'jsoncol', new JsonExpression(['nil' => null])],
                '[[jsoncol]] = :qp0', [':qp0' => '{"nil":null}']
            ],
            'with object as value' => [
                ['=', 'jsoncol', new JsonExpression(new DynamicModel(['a' => 1, 'b' => 2]))],
                '[[jsoncol]] = :qp0', [':qp0' => '{"a":1,"b":2}']
            ],
            'query' => [
                ['=', 'jsoncol', new JsonExpression((new Query())->select('params')->from('user')->where(['id' => 1]))],
                '[[jsoncol]] = (SELECT [[params]] FROM [[user]] WHERE [[id]]=:qp0)', [':qp0' => 1]
            ],
            'query with type, that is ignored in MySQL' => [
                ['=', 'jsoncol', new JsonExpression((new Query())->select('params')->from('user')->where(['id' => 1]), 'jsonb')],
                '[[jsoncol]] = (SELECT [[params]] FROM [[user]] WHERE [[id]]=:qp0)', [':qp0' => 1]
            ],
            'nested and combined json expression' => [
                ['=', 'jsoncol', new JsonExpression(new JsonExpression(['a' => 1, 'b' => 2, 'd' => new JsonExpression(['e' => 3])]))],
                "[[jsoncol]] = :qp0", [':qp0' => '{"a":1,"b":2,"d":{"e":3}}']
            ],
            'search by property in JSON column (issue #15838)' => [
                ['=', new Expression("(jsoncol->>'$.someKey')"), '42'],
                "(jsoncol->>'$.someKey') = :qp0", [':qp0' => 42]
            ]
        ]);
    }
}