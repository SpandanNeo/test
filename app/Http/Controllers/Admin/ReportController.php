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


class ReportController extends Controller
{   

     var $column_order = array('type', 'username', 'title','description', 'report_on'); //set column field database for datatable orderable

    var $column_search = array('type', 'u.username', 'v.title','r.description','report_on'); //set column field database for datatable searchable

    var $order = array('r.report_id' => 'desc'); // default order

    public function __construct() {
        $this->middleware('app_version_check', ['only' => ['edit','delete']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $menu='Reports';
        $menuUrl=route('admin.reports.index');
        return view("admin.reports",compact('menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
       
    }

    private function _form_validation($request){
   
       
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    
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
        
    }

  
    public function view($id)
    {
    
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
        
    }

  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   
     public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')).'/reports/';

        $list = $this->get_datatables($request);
        $data = array();
        $no = $request->start;
        foreach ($list as $category) {
            $no++;
            $row = array();
            // $row[] = '<a class="view" href="'.$currentPath.$category->report_id.'/'.'view"><i class="fa fa-search"></i></a><a class="edit" href="'.$currentPath.$category->report_id.'/edit"><i class="fa fa-edit"></i></a><a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$category->report_id.'"><i class="fa fa-trash"></i></a>';
            // $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$category->report_id.'"><label for="cb'.$no.'"></label></div>';
            // $row[] = $category->title;
            $row[] = $category->type;
                $html="<i class='fa fa-play-circle-o video_play' aria-hidden='true'></i>";
            $row[] = "<div style='position:relative;text-align:center;'>".$html."<img src=".asset(Storage::url('public/videos/'.$category->user_id.'/thumb/'.$category->thumb))." height=200 data-bs-toggle='modal' data-bs-target='#homeVideo' class='video_thumb' id='".asset(Storage::url('public/videos/'.$category->user_id.'/'.$category->video))."'/></div>";
            $row[] = $category->username;
            $row[] = $category->description;
            $row[] = $category->report_on;
            if($category->active==1){
                $active="checked";
            }else{
                $active="";
            }
            $row[] = '<input type="checkbox" class="active_toggle" '.$active.' data-id="'.$category->video_id.'" data-bs-toggle="toggle" data-on="Yes" data-off="No" data-onstyle="success" data-offstyle="danger" >';
            if($category->user_active==1){
                $uactive="checked";
            }else{
                $uactive="";
            }
            $row[] = '<input type="checkbox" class="user_active_toggle" '.$uactive.' data-id="'.$category->user_id.'" data-bs-toggle="toggle" data-on="Yes" data-off="No" data-onstyle="success" data-offstyle="danger" >';
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
        $candidateRS = DB::table('reports as r')
                        ->leftJoin('users as u' , 'u.user_id','=','r.user_id')
                        ->leftJoin('videos as v' , 'r.video_id','=','v.video_id')
                        ->leftJoin('users as u2' , 'u2.user_id','=','v.user_id')
                       ->select(DB::raw("r.report_id as report_id,r.type as type,r.description as description,r.report_on as report_on,u.username as username,v.title as title,v.active as active,v.video_id as video_id,u2.user_id as user_id,u2.active as user_active,v.video as video,v.thumb as thumb"));
                        
        $strWhere = " v.deleted=0";
        $strWhereOr = "";
        $i = 0;

        foreach ($this->column_search as $item) // loop column
        {
            if($keyword) // if datatable send POST for search{
            	$strWhereOr = $strWhereOr." $item like '%".$keyword."%' or ";
        }
        $strWhereOr = trim($strWhereOr, "or ");
        if($strWhereOr!=""){
	        $candidateRS = $candidateRS->whereRaw(DB::raw($strWhere." and (".$strWhereOr.")"));
	    }else{
			$candidateRS = $candidateRS->whereRaw(DB::raw($strWhere	));
		}
        

        if(isset($order)) // here order processing
        {
            $candidateRS = $candidateRS->orderBy($this->column_order[$request->order['0']['column']], $request->order['0']['dir']);
        } 
        else if(isset($this->order))
        {
            $orderby = $this->order;
            $candidateRS = $candidateRS->orderBy(key($orderby),$orderby[key($orderby)]);
        }
       
        return $candidateRS;
    }

    function get_datatables($request)
    {
        $candidateRS = $this->_get_datatables_query($request);
        if($request->length != -1){
            $candidateRS = $candidateRS->limit($request->length);
            if($request->start != -1){
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
        $candidateRS = DB::table('reports as r')->select(DB::raw("count(*) as total"))
                                ->leftJoin('users as u' , 'u.user_id','=','r.user_id')
                                ->leftJoin('videos as v' , 'r.video_id','=','v.video_id')
                                ->where('v.deleted',0)
                                ->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
    
    }

    public function copyContent($id)
    {
       
    }
}
