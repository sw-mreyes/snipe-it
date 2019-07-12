<?php

namespace App\Presenters;

use App\Helpers\Helper;
use function GuzzleHttp\json_encode;

/**
 * Class ReservationPresenter
 * @package App\Presenters
 */
class ReservationPresenter extends Presenter
{

    /**
     * Json Column Layout for bootstrap table
     * @return string
     */
    public static function dataTableLayout()
    {
        $layout = [
            [
                "field" => "checkbox",
                "checkbox" => true
            ],
            [
                "field" => "id",
                "searchable" => false,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.id'),
                "visible" => true
            ], [
                "field" => "name",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('general.name'),
                "visible" => true,
                "formatter" => "reservationsLinkFormatter"
            ], [
                "field" => "start",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('reservations.start'),
                "visible" => true,
                //"formatter" => "dateDisplayFormatter"
            ], [
                "field" => "end",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('reservations.end'),
                "visible" => true,
                //"formatter" => "dateDisplayFormatter"
            ], [
                "field" => "user",
                "searchable" => false,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.user'),
                "visible" => true,
                "formatter" => "resUserNameFormatter"
            ], [
                "field" => "assets",
                "searchable" => false,
                "sortable" => false,
                "switchable" => true,
                "title" => trans('general.assets'),
                "visible" => true
            ], [
                "field" => "actions",
                "searchable" => false,
                "sortable" => false,
                "switchable" => false,
                "title" => trans('table.actions'),
                "formatter" => "reservationsActionsFormatter",
            ]
        ];
        return json_encode($layout);
    }

    /**
     * Json Column Layout for bootstrap table of the reservations' assets.
     */
    public static function assetTableLayout()
    {
        $layout = [
            [
                "field" => "checkbox",
                "checkbox" => true
            ], [
                "field" => "id",
                "searchable" => false,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.id'),
                "visible" => false
            ], [
                "field" => "company",
                "searchable" => true,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('general.company'),
                "visible" => false,
                "formatter" => 'assetCompanyObjFilterFormatter'
            ], [
                "field" => "name",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.name'),
                "visible" => true,
                "formatter" => "hardwareLinkFormatter"
            ], [
                "field" => "image",
                "searchable" => false,
                "sortable" => true,
                "switchable" => true,
                "title" => trans('admin/hardware/table.image'),
                "visible" => true,
                "formatter" => "imageFormatter"
            ], [
                "field" => "asset_tag",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/table.asset_tag'),
                "visible" => true,
                "formatter" => "hardwareLinkFormatter"
            ], [
                "field" => "serial",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.serial'),
                "visible" => true,
                "formatter" => "hardwareLinkFormatter"
            ],  [
                "field" => "model",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.model'),
                "visible" => true,
                "formatter" => "modelsLinkObjFormatter"
            ], [
                "field" => "model_number",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/models/table.modelnumber'),
                "visible" => false
            ], [
                "field" => "category",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('general.category'),
                "visible" => true,
                "formatter" => "categoriesLinkObjFormatter"
            ], [
                "field" => "status_label",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/table.status'),
                "visible" => true,
                "formatter" => "statuslabelsLinkObjFormatter"
            ], [
                "field" => "assigned_to",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.checkedout_to'),
                "visible" => true,
                "formatter" => "polymorphicItemFormatter"
            ], [
                "field" => "employee_number",
                "searchable" => false,
                "sortable" => false,
                "title" => trans('admin/users/table.employee_num'),
                "visible" => false,
                "formatter" => "employeeNumFormatter"
            ], [
                "field" => "location",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/table.location'),
                "visible" => true,
                "formatter" => "deployedLocationFormatter"
            ], [
                "field" => "rtd_location",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('admin/hardware/form.default_location'),
                "visible" => false,
                "formatter" => "deployedLocationFormatter"
            ], [
                "field" => "manufacturer",
                "searchable" => true,
                "sortable" => true,
                "title" => trans('general.manufacturer'),
                "visible" => false,
                "formatter" => "manufacturersLinkObjFormatter"
            ], [
                "field" => "notes",
                "searchable" => true,
                "sortable" => true,
                "visible" => false,
                "title" => trans('general.notes'),

            ], [
                "field" => "last_checkout",
                "searchable" => false,
                "sortable" => true,
                "visible" => true,
                "title" => trans('admin/hardware/table.checkout_date'),
                "formatter" => "dateDisplayFormatter"
            ], [
                "field" => "expected_checkin",
                "searchable" => false,
                "sortable" => true,
                "visible" => true,
                "title" => trans('admin/hardware/form.expected_checkin'),
                "formatter" => "dateDisplayFormatter"
            ]
        ];

        return json_encode($layout);
    }


    /**
     * Link to this reservations name
     * @return string
     */
    public function nameUrl()
    {
        return (string) link_to_route('reservations.show', $this->name, $this->id);
    }

    /**
     * Getter for Polymorphism.
     * @return mixed
     */
    public function name()
    {
        return $this->model->name;
    }

    /**
     * Url to view this item.
     * @return string
     */
    public function viewUrl()
    {
        return route('reservations.show', $this->id);
    }

    public function glyph()
    {
        return '<i class="fa fa-map-marker"></i>';
    }

    public function fullName()
    {
        return $this->name;
    }
}
