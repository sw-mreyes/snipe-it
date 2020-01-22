<?php

namespace App\Presenters;

use App\Helpers\Helper;
use Illuminate\Support\Facades\Gate;

/**
 * Class AccessoryPresenter
 * @package App\Presenters
 */
class SearchResultPresenter extends Presenter
{
    /**
     * Json Column Layout for bootstrap table
     * @return string
     */
    public static function dataTableLayout()
    {
        $layout = [
            [
                "field" => "type",
                "searchable" => true,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.type'),
                "visible" => true,
            ],
            [
                "field" => "tag",
                "searchable" => false,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.tag'),
                "visible" => true,
                "formatter" => "assetTagLinkFormatter",
            ],
            [
                "field" => "model",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.model'),
                "switchable" => true,
                "formatter" => "modelsLinkObjFormatter"
            ],
            [
                "field" => "name",
                "searchable" => true,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.name'),
                "visible" => true,
            ],
            [
                "field" => "assigned_to",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.checkedout_to'),
                "visible" => true,
                "formatter" => "polymorphicItemFormatter"
            ], [
                "field" => "actions",
                "searchable" => false,
                "sortable" => false,
                "switchable" => false,
                "title" => trans('table.actions'),
                "formatter" => "searchResultActionFormatter",
            ]
        ];

        return json_encode($layout);
    }

}
