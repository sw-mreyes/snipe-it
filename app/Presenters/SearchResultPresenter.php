<?php

namespace App\Presenters;

/**
 * Column layout for the global search results table (custom fork feature).
 *
 * The rows are heterogeneous — assets, accessories, components, consumables,
 * locations, categories and asset models share one table — so every column has
 * to tolerate a null value for the types it does not apply to.
 */
class SearchResultPresenter extends Presenter
{
    /**
     * JSON column layout for bootstrap-table.
     */
    public static function dataTableLayout(): string
    {
        $layout = [
            [
                'field' => 'type',
                'searchable' => false,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('general.type'),
                'visible' => true,
                'formatter' => 'searchTypeFormatter',
            ], [
                'field' => 'tag',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('global-search.tag'),
                'visible' => true,
                'formatter' => 'searchNameFormatter',
            ], [
                'field' => 'name',
                'searchable' => false,
                'sortable' => true,
                'switchable' => false,
                'title' => trans('general.name'),
                'visible' => true,
                'formatter' => 'searchNameFormatter',
            ], [
                'field' => 'category',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.category'),
                'visible' => true,
            ], [
                'field' => 'location',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('general.location'),
                'visible' => true,
            ], [
                'field' => 'assigned_to',
                'searchable' => false,
                'sortable' => true,
                'switchable' => true,
                'title' => trans('admin/hardware/form.checkedout_to'),
                'visible' => true,
            ], [
                'field' => 'actions',
                'searchable' => false,
                'sortable' => false,
                'switchable' => false,
                'title' => trans('table.actions'),
                'visible' => true,
                'formatter' => 'searchActionsFormatter',
            ],
        ];

        return json_encode($layout);
    }
}
