<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Common\Functions;
use Mail;
use FFMpeg;
use FFProbe;


class PageController extends Controller
{

    var $column_order = array(null, 'title'); //set column field database for datatable orderable

    var $column_search = array('title'); //set column field database for datatable searchable

    var $order = array('page_id' => 'asc'); // default order

    public function __construct()
    {
        $this->middleware('app_version_check', ['only' => ['edit', 'delete']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string .= $key . "\n";
        }
        return $error_string;
    }


    public function index()
    {
        $menu='Pages';
        $menuUrl=route('admin.pages.index');
        return view("admin.pages",compact('menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $action = 'add';

        $action = 'add';    
        $menu='Pages';
        $menuUrl=route('admin.pages.index');

        $submenu='Add Page';
        $submenuUrl=route('admin.pages.create');

        return view('admin.page-create', compact('action','menu','menuUrl','submenu','submenuUrl'));
    }

    private function _form_validation($request)
    {

        $rules = [
            'title'        => 'required',
            'content'          => 'required',
            'type'          => 'required',
        ];
        $messages = [
            'title.required'   => 'Title is required',
            'content.required'              => 'Content is required',
            'type.required'              => 'Type is required',
        ];
        $this->validate($request, $rules, $messages);

        $postData = array(
            'title'      => $request->title,
            'content'      => $request->content,
            'type'      => $request->type,

        );

        return $postData;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->_form_validation($request);
        DB::table('pages')->insert($data);
        return redirect(config('app.admin_url') . '/pages')->with('success', 'Page Content submitted successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $action = 'edit';
        $menu='Pages';
        $menuUrl=route('admin.pages.index');

        $submenu='Edit Page';
        $submenuUrl=route('admin.pages.edit',$id);

        $page = DB::table('pages')->select(DB::raw("*"))->where('page_id', '=', $id)->first();
        return view('admin.page-create', compact('page', 'id', 'action','menu','menuUrl','submenu','submenuUrl'));
    }


    public function view($id)
    {
        $action = 'view';   
        $menu='Pages';
        $menuUrl=route('admin.pages.index');

        $submenu='View Page';
        $submenuUrl=route('admin.pages_view',$id);

        $page = DB::table('pages')->select(DB::raw("*"))->where('page_id', '=', $id)->first();

        return view('admin.page-create', compact('page', 'id', 'action','menu','menuUrl','submenu','submenuUrl'));
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $this->_form_validation($request);

        DB::table('pages')->where('page_id', $id)->update($data);
        return redirect(config('app.admin_url') . '/pages')->with('success', 'Page Content updated successfully');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')) . '/pages/';

        $list = $this->get_datatables($request);
        $data = array();
        $no = $request->start;
        foreach ($list as $content) {
            $no++;
            $row = array();
            $row[] = '<div class="align-center"><input id="cb' . $no . '" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="' . $content->page_id . '"><label for="cb' . $no . '"></label></div>';
            $row[] = $content->title;
            $row[] = '<a class="view btn btn-success green-bg text-white" href="' . $currentPath . $content->page_id . '/' . 'view"><i class="fa fa-search"></i></a> <a class="edit btn btn-warning button-green text-white" href="' . $currentPath . $content->page_id . '/edit"><i class="fa fa-edit"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="' . $content->page_id . '"><i class="fa fa-trash"></i></a>';

            $data[] = $row;
        }

        $output = array(
            "draw" => $request->draw,
            "recordsTotal" => $this->count_all($request),
            "recordsFiltered" => $this->count_filtered($request),
            "data" => $data,
        );
        echo json_encode($output);
    }

    private function _get_datatables_query($request)
    {
        $keyword = $request->search['value'];
        $order = $request->order;
        $candidateRS = DB::table('pages')
            ->select(DB::raw("*"));

        $strWhere = " page_id!=0 ";
        $strWhereOr = "";
        $i = 0;

        foreach ($this->column_search as $item) // loop column
        {
            if ($keyword) // if datatable send POST for search{
                $strWhereOr = $strWhereOr . " $item like '%" . $keyword . "%' or ";
        }
        $strWhereOr = trim($strWhereOr, "or ");
        if ($strWhereOr != "") {
            $candidateRS = $candidateRS->whereRaw(DB::raw($strWhere . " and (" . $strWhereOr . ")"));
        } else {
            $candidateRS = $candidateRS->whereRaw(DB::raw($strWhere));
        }


        if (isset($order)) // here order processing
        {
            $candidateRS = $candidateRS->orderBy($this->column_order[$request->order['0']['column']], $request->order['0']['dir']);
        } else if (isset($this->order)) {
            $orderby = $this->order;
            $candidateRS = $candidateRS->orderBy(key($orderby), $orderby[key($orderby)]);
        }

        return $candidateRS;
    }

    function get_datatables($request)
    {
        $candidateRS = $this->_get_datatables_query($request);

        if ($request->length != -1) {
            $candidateRS = $candidateRS->limit($request->length);
            if ($request->start != -1) {
                $candidateRS = $candidateRS->offset($request->start);
            }
        }

        $candidates = $candidateRS->get();

        return $candidates;
    }

    function count_filtered($request)
    {
        $candidateRS = $this->_get_datatables_query($request);
        return $candidateRS->count();
    }

    public function count_all($request)
    {
        $candidateRS = DB::table('pages')->select(DB::raw("count(*) as total"))->first();
        return $candidateRS->total;
    }

    public function delete(Request $request)
    {
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',', $request->ids);
        foreach ($ids as $id) {
            DB::table('pages')->where('page_id', $id)->delete();
        }

        if ($del_error == 'error') {
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error', "rec_exists" => $rec_exists]);
        } else {
            if (count($ids) > 1) {
                $msg = "Page deleted successfully";
            } else {
                $msg = "Page deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success', "rec_exists" => $rec_exists]);
        }
        return redirect()->back();
    }

    public function copyContent($id)
    {
        $action = 'copy';
        // $parent_categories = DB::table('categories')
        //     ->select(DB::raw('cat_id,cat_name,parent_id'))
        //     ->where('parent_id',0)
        //     ->orderBy('cat_id','ASC')
        //     ->get();
        // $categories = DB::table('categories')
        //         ->select(DB::raw('cat_id,cat_name,parent_id'))
        //         ->where('parent_id','!=',0)
        //         ->orderBy('cat_id','ASC')
        //         ->get();
        $page = DB::table('pages')->select(DB::raw("*"))->where('page_id', '=', $id)->first();
        return view('admin.page-create', compact('id', 'page'));
    }
}
