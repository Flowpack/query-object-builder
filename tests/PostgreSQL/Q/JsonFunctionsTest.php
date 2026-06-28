<?php

declare(strict_types=1);

use Flowpack\QueryObjectBuilder\PostgreSQL\Q;

describe('JsonSetReturningFunctions', function () {
    it('renders jsonb_array_elements in FROM', function () {
        $q = Q::select(Q::n('value'))->from(Q\Func::jsonbArrayElements(Q::n('my_column')));

        expect($q)->toRenderSql('SELECT value FROM jsonb_array_elements(my_column)', null);
    });

    it('renders json_array_elements in FROM', function () {
        $q = Q::select(Q::n('value'))->from(Q\Func::jsonArrayElements(Q::n('data')));

        expect($q)->toRenderSql('SELECT value FROM json_array_elements(data)', null);
    });

    it('renders jsonb_array_elements_text in FROM', function () {
        $q = Q::select(Q::n('value'))->from(Q\Func::jsonbArrayElementsText(Q::n('my_column')));

        expect($q)->toRenderSql('SELECT value FROM jsonb_array_elements_text(my_column)', null);
    });

    it('renders json_array_elements_text in FROM', function () {
        $q = Q::select(Q::n('value'))->from(Q\Func::jsonArrayElementsText(Q::n('data')));

        expect($q)->toRenderSql('SELECT value FROM json_array_elements_text(data)', null);
    });

    it('renders jsonb_array_elements with alias', function () {
        $q = Q::select(Q::n('elem'))->from(Q\Func::jsonbArrayElements(Q::n('my_column')))->as('elem');

        expect($q)->toRenderSql('SELECT elem FROM jsonb_array_elements(my_column) AS elem', null);
    });

    it('renders jsonb_each in FROM', function () {
        $q = Q::select(Q::n('key'), Q::n('value'))->from(Q\Func::jsonbEach(Q::n('data')));

        expect($q)->toRenderSql('SELECT key, value FROM jsonb_each(data)', null);
    });

    it('renders json_each in FROM', function () {
        $q = Q::select(Q::n('key'), Q::n('value'))->from(Q\Func::jsonEach(Q::n('data')));

        expect($q)->toRenderSql('SELECT key, value FROM json_each(data)', null);
    });

    it('renders jsonb_each_text in FROM', function () {
        $q = Q::select(Q::n('key'), Q::n('value'))->from(Q\Func::jsonbEachText(Q::n('data')));

        expect($q)->toRenderSql('SELECT key, value FROM jsonb_each_text(data)', null);
    });

    it('renders json_each_text in FROM', function () {
        $q = Q::select(Q::n('key'), Q::n('value'))->from(Q\Func::jsonEachText(Q::n('data')));

        expect($q)->toRenderSql('SELECT key, value FROM json_each_text(data)', null);
    });

    it('renders jsonb_object_keys in FROM', function () {
        $q = Q::select(Q::n('jsonb_object_keys'))->from(Q\Func::jsonbObjectKeys(Q::n('data')));

        expect($q)->toRenderSql('SELECT jsonb_object_keys FROM jsonb_object_keys(data)', null);
    });

    it('renders json_object_keys in FROM', function () {
        $q = Q::select(Q::n('json_object_keys'))->from(Q\Func::jsonObjectKeys(Q::n('data')));

        expect($q)->toRenderSql('SELECT json_object_keys FROM json_object_keys(data)', null);
    });

    it('renders jsonb_populate_recordset in FROM', function () {
        $q = Q::select(Q::n('*'))->from(Q\Func::jsonbPopulateRecordset(Q::null(), Q::n('data')));

        expect($q)->toRenderSql('SELECT * FROM jsonb_populate_recordset(NULL, data)', null);
    });

    it('renders json_populate_recordset in FROM', function () {
        $q = Q::select(Q::n('*'))->from(Q\Func::jsonPopulateRecordset(Q::null(), Q::n('data')));

        expect($q)->toRenderSql('SELECT * FROM json_populate_recordset(NULL, data)', null);
    });

    it('renders jsonb_path_query in FROM', function () {
        $q = Q::select(Q::n('*'))->from(Q\Func::jsonbPathQuery(Q::n('data'), Q::string('$.items[*]')));

        expect($q)->toRenderSql("SELECT * FROM jsonb_path_query(data, '\$.items[*]')", null);
    });

    it('renders jsonb_path_query_tz in FROM', function () {
        $q = Q::select(Q::n('*'))->from(Q\Func::jsonbPathQueryTz(Q::n('data'), Q::string('$.items[*]')));

        expect($q)->toRenderSql("SELECT * FROM jsonb_path_query_tz(data, '\$.items[*]')", null);
    });

    it('renders jsonb_array_elements as expression', function () {
        $q = Q::select(Q\Func::jsonbArrayElements(Q::n('data')));

        expect($q)->toRenderSql('SELECT jsonb_array_elements(data)', null);
    });

    it('renders jsonb_array_elements with cross join', function () {
        $q = Q::select(Q::n('t.id'), Q::n('elem'))
            ->from(Q::n('my_table'))->as('t')
            ->from(Q\Func::jsonbArrayElements(Q::n('t.data')))->as('elem');

        expect($q)->toRenderSql('SELECT t.id, elem FROM my_table AS t, jsonb_array_elements(t.data) AS elem', null);
    });
});
