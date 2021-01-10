<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 7/30/2019
 * Time: 1:56 PM
 */
namespace Modules\Event\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\AdminController;
use Modules\Event\Models\Event;
use Modules\Event\Models\EventTerm;
use Modules\Event\Models\EventTranslation;
use Modules\Core\Models\Attributes;
use Modules\Location\Models\Location;

class EventController extends AdminController
{
    protected $event;
    protected $event_translation;
    protected $event_term;
    protected $attributes;
    protected $location;
    public function __construct()
    {
        parent::__construct();
        $this->setActiveMenu(route('event.admin.index'));
        $this->event = Event::class;
        $this->event_translation = EventTranslation::class;
        $this->event_term = EventTerm::class;
        $this->attributes = Attributes::class;
        $this->location = Location::class;
    }

    public function callAction($method, $parameters)
    {
        if(!Event::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function index(Request $request)
    {
        $this->checkPermission('event_view');
        $query = $this->event::query() ;
        $query->orderBy('id', 'desc');
        if (!empty($s = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $s . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('event_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'event_manage_others' => $this->hasPermission('event_manage_others'),
            'breadcrumbs'        => [
                [
                    'name' => __('Events'),
                    'url'  => 'admin/module/event'
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Event Management")
        ];
        return view('Event::admin.index', $data);
    }

    public function recovery(Request $request)
    {
        $this->checkPermission('event_view');
        $query = $this->event::onlyTrashed() ;
        $query->orderBy('id', 'desc');
        if (!empty($s = $request->input('s'))) {
            $query->where('title', 'LIKE', '%' . $s . '%');
            $query->orderBy('title', 'asc');
        }

        if ($this->hasPermission('event_manage_others')) {
            if (!empty($author = $request->input('vendor_id'))) {
                $query->where('create_user', $author);
            }
        } else {
            $query->where('create_user', Auth::id());
        }
        $data = [
            'rows'               => $query->with(['author'])->paginate(20),
            'event_manage_others' => $this->hasPermission('event_manage_others'),
            'recovery'           => 1,
            'breadcrumbs'        => [
                [
                    'name' => __('Events'),
                    'url'  => 'admin/module/event'
                ],
                [
                    'name'  => __('Recovery'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Recovery Event Management")
        ];
        return view('Event::admin.index', $data);
    }

    public function create(Request $request)
    {
        $this->checkPermission('event_create');
        $row = new $this->event();
        $row->fill([
            'status' => 'publish'
        ]);
        $data = [
            'row'            => $row,
            'attributes'     => $this->attributes::where('service', 'event')->get(),
            'event_location' => $this->location::where('status', 'publish')->get()->toTree(),
            'translation'    => new $this->event_translation(),
            'breadcrumbs'    => [
                [
                    'name' => __('Events'),
                    'url'  => route('event.admin.index')
                ],
                [
                    'name'  => __('Add Event'),
                    'class' => 'active'
                ],
            ],
            'page_title'     => __("Add new Event")
        ];
        return view('Event::admin.detail', $data);
    }

    public function edit(Request $request, $id)
    {
        $this->checkPermission('event_update');
        $row = $this->event::find($id);
        if (empty($row)) {
            return redirect(route('event.admin.index'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        if (!$this->hasPermission('event_manage_others')) {
            if ($row->create_user != Auth::id()) {
                return redirect(route('event.admin.index'));
            }
        }
        $data = [
            'row'            => $row,
            'translation'    => $translation,
            "selected_terms" => $row->terms->pluck('term_id'),
            'attributes'     => $this->attributes::where('service', 'event')->get(),
            'event_location'  => $this->location::where('status', 'publish')->get()->toTree(),
            'enable_multi_lang'=>true,
            'breadcrumbs'    => [
                [
                    'name' => __('Events'),
                    'url'  => route('event.admin.index')
                ],
                [
                    'name'  => __('Edit Event'),
                    'class' => 'active'
                ],
            ],
            'page_title'=>__("Edit: :name",['name'=>$row->title])
        ];
        return view('Event::admin.detail', $data);
    }

    public function store( Request $request, $id ){

        if($id>0){
            $this->checkPermission('event_update');
            $row = $this->event::find($id);
            if (empty($row)) {
                return redirect(route('event.admin.index'));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('event_manage_others'))
            {
                return redirect(route('event.admin.index'));
            }
        }else{
            $this->checkPermission('event_create');
            $row = new $this->event();
            $row->status = "publish";
        }
        $dataKeys = [
            'title',
            'content',
            'price',
            'is_instant',
            'status',
            'video',
            'faqs',
            'image_id',
            'banner_image_id',
            'gallery',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',

            'duration',
            'start_time',
            'price',
            'sale_price',
            'ticket_types',

            'enable_extra_price',
            'extra_price',
            'is_featured',
            'default_state',
        ];
        if($this->hasPermission('event_manage_others')){
            $dataKeys[] = 'create_user';
        }

        $row->fillByAttr($dataKeys,$request->input());
        if($request->input('slug')){
            $row->slug = $request->input('slug');
        }

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);

        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }

            if($id > 0 ){
                return back()->with('success',  __('Event updated') );
            }else{
                return redirect(route('event.admin.edit',$row->id))->with('success', __('Event created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        $this->checkPermission('event_manage_attributes');
        if (empty($request->input('terms'))) {
            $this->event_term::where('target_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->event_term::firstOrCreate([
                    'term_id' => $term_id,
                    'target_id' => $row->id
                ]);
            }
            $this->event_term::where('target_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function bulkEdit(Request $request)
    {

        $ids = $request->input('ids');
        $action = $request->input('action');
        if (empty($ids) or !is_array($ids)) {
            return redirect()->back()->with('error', __('No items selected!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }

        switch ($action){
            case "delete":
                foreach ($ids as $id) {
                    $query = $this->event::where("id", $id);
                    if (!$this->hasPermission('event_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('event_delete');
                    }
                    $query->first();
                    if(!empty($query)){
                        $query->delete();
                    }
                }
                return redirect()->back()->with('success', __('Deleted success!'));
                break;
            case "recovery":
                foreach ($ids as $id) {
                    $query = $this->event::where("id", $id);
                    if (!$this->hasPermission('event_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('event_delete');
                    }
                    $query->first();
                    if(!empty($query)){
                        $query->restore();
                    }
                }
                return redirect()->back()->with('success', __('Recovery success!'));
                break;
            case "clone":
                $this->checkPermission('event_create');
                foreach ($ids as $id) {
                    (new $this->event())->saveCloneByID($id);
                }
                return redirect()->back()->with('success', __('Clone success!'));
                break;
            default:
                // Change status
                foreach ($ids as $id) {
                    $query = $this->event::where("id", $id);
                    if (!$this->hasPermission('event_manage_others')) {
                        $query->where("create_user", Auth::id());
                        $this->checkPermission('event_update');
                    }
                    $query->update(['status' => $action]);
                }
                return redirect()->back()->with('success', __('Update success!'));
                break;
        }


    }
}
