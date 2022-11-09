<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 
use Mail;

class CategoryController extends Controller
{   

     var $column_order = array(null, 'cat_name','rank','parent_name',null); //set column field database for datatable orderable

    var $column_search = array('c.cat_name','c2.cat_name'); //set column field database for datatable searchable

    var $order = array('c.cat_id' => 'asc'); // default order

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
        $menu='Categories';
        $menuUrl=route('admin.categories.index');
        return view("admin.categories",compact(['menu','menuUrl']));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $menu='Categories';
        $menuUrl=route('admin.categories.index');

        $submenu='Add Category';
        $submenuUrl=route('admin.categories.create');

        $action = 'add';
        $patent_cats = DB::table('categories')
                        ->select(DB::raw('cat_id,cat_name'))
                        ->where('parent_id',0)
                        ->orderBy('cat_name','ASC')
                        ->get();
                       // dd($patent_cats);
        return view('admin.categories-create',compact('action','patent_cats','menu','menuUrl','submenu','submenuUrl'));
    }

    private function _form_validation($request){
        $rules = [
            'cat_name' => 'required',
           // 'rank'     => 'required',
            
        ];
        $messages = [
            'cat_name.required' => 'You can\'t leave category name field empty',
           // 'rank.required'    => 'You can\'t leave rank field empty'
            
        ]; 
        $this->validate($request,$rules,$messages);
        
        if($request->rank==""){
            $rank_res = DB::table('categories')
                ->select(DB::raw('MAX(rank) as rank'))
                ->first();
            $rank=$rank_res->rank + 1;
        }else{
            $rank=$request->rank;
        }
        $postData = array(
            'parent_id' => $request->parent_id,
            'cat_name' => $request->cat_name,
            'rank'     => $rank,
            'added_on' => date('Y-m-d H:i:s')
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
        //dd($data); 
        DB::table('categories')->insert($data);
        return redirect( config('app.admin_url').'/categories')->with('success','Category details submitted successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
    {   
        $categories = DB::table('categories')
        ->select(DB::raw('cat_id,category'))
        //->where('type',$type)
        ->orderBy('cat_id','DESC')
        ->orderBy('rank','DESC')
        ->get();
       
        return view("admin.categories",compact('categories','type'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $menu='Categories';
        $menuUrl=route('admin.categories.index');

        $submenu='Edit Category';
        $submenuUrl=route('admin.categories.edit',$id);

        $action = 'edit';
        $patent_cats = DB::table('categories')
                ->select(DB::raw('cat_id,cat_name'))
                ->where('parent_id',0)
                ->orderBy('cat_name','ASC')
                ->get();
        $category = DB::table('categories')->select(DB::raw("*"))->where('cat_id','=',$id)->first();
        return view('admin.categories-create',compact('category','id','action','patent_cats','menu','menuUrl','submenu','submenuUrl'));
    }

    public function view($id)
    {
        $menu='Categories';
        $menuUrl=route('admin.categories.index');

        $submenu='View Category';
        $submenuUrl=route('admin.categories_view',$id);

        $action = 'view';
        $patent_cats = DB::table('categories')
        ->select(DB::raw('cat_id,cat_name'))
        ->where('parent_id',0)
        ->orderBy('cat_name','ASC')
        ->get();
        $category = DB::table('categories')->select(DB::raw("*"))->where('cat_id','=',$id)->first();
                 //dd($candidate);
        return view('admin.categories-create',compact('category','id','action','patent_cats','menu','menuUrl','submenu','submenuUrl'));
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
        DB::table('categories')->where('cat_id',$id)->update($data);
        return redirect( config('app.admin_url').'/categories')->with('success','categorie details updated successfully');
    }

  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   
     public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')).'/categories/';

        $list = $this->get_datatables($request);
		//dd($list);
        $data = array();
        $no = $request->start;
        foreach ($list as $category) {
            $no++;
            $row = array();
            
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$category->cat_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $category->cat_name;
            $row[] = $category->rank;
            $row[] = $category->parent_name;
            $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.$category->cat_id.'/'.'view"><i class="fa fa-search"></i></a> <a class="edit btn btn-warning button-green text-white" href="'.$currentPath.$category->cat_id.'/edit"><i class="fa fa-edit"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$category->cat_id.'"><i class="fa fa-trash"></i></a>';
           // $row[] = '<a href="#" data-bs-toggle="modal" data-bs-target="#modal1" class="btn btn-primary process" data-val="'.$category->cat_id.'">Visible</a>';
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
        $candidateRS = DB::table('categories as c')
                        ->leftJoin('categories as c2', 'c2.cat_id', '=', 'c.parent_id')
                       ->select(DB::raw("c.*,c2.cat_name as parent_name"));
                        
        $strWhere = " c.deleted=0";
        $strWhereOr = "";
        $i = 0;

        foreach ($this->column_search as $item) // loop column
        {
            if($keyword) // if datatable send POST for search{
            	$strWhereOr = $strWhereOr." $item like '%".$keyword."%' or ";
                //$candidateRS = $candidateRS->orWhere($item, 'like', '%' . $keyword . '%') ;
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
       // dd($candidates);
        return $candidates;
    }

    function count_filtered($request)
    {
        $candidateRS = $this->_get_datatables_query($request);
        return $candidateRS->count();
    }

    public function count_all($request)
    {
        $candidateRS = DB::table('categories')->select(DB::raw("count(*) as total"))->where('deleted',0)->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        foreach ($ids as $id) {
            DB::table('categories')->where('cat_id', $id)->delete();
        }
        
        if($del_error == 'error'){
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error',"rec_exists"=>$rec_exists]);
        }else{
            if( count($ids) > 1){
                $msg = "Category deleted successfully";
            }else{
                $msg = "Category deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success',"rec_exists"=>$rec_exists]);
        }
        return redirect()->back();
    }

    public function copyContent($id)
    {
        $action = 'copy';
        $patent_cats = DB::table('categories')
            ->select(DB::raw('cat_id,cat_name'))
            ->where('parent_id',0)
            ->orderBy('cat_name','ASC')
            ->get();
        $category = DB::table('categories')->select(DB::raw("*"))->where('cat_id','=',$id)->first();
        // dd($category);
        return view('admin.categories-create',compact('id','category','action','patent_cats'));
    }
}
