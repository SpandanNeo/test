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


class SponsorController extends Controller
{   

     var $column_order = array(null,'heading','image','title',null); //set column field database for datatable orderable

    var $column_search = array('heading','image','title'); //set column field database for datatable searchable

    var $order = array('sponsor_id' => 'asc'); // default order

    public function __construct() {
        $this->middleware('app_version_check', ['only' => ['edit','delete']]);
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
            $error_string.= $key."\n";
        }
        return $error_string;
    }
    
    
    public function index()
    {
        $menu='Sponsors';
        $menuUrl=route('admin.sponsors.index');
        return view("admin.sponsors",compact('menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $menu='Sponsors';
        $menuUrl=route('admin.sponsors.index');

        $submenu='Add Sponsor';
        $submenuUrl=route('admin.sponsors.create');

        $action = 'add';            
        return view('admin.sponsors-create',compact('action','menu','menuUrl','submenu','submenuUrl'));
    }

    private function _form_validation($request){
      
        $validator = Validator::make($request->all(), [   
            'heading'        => 'required',
            'title'        => 'required',
            'url'        => 'required',
            'image'          => 'image|mimes:jpeg,png,jpg,gif,svg',             
        ],[ 
            
            'heading.required'  => 'Heading is required',
            'title.required'    => 'Title is required',
            'url.required'      => 'Url is required',
            'image.required'	=> 'Image is required',         

        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
           
            $functions = new Functions();
            if($request->id>0 || isset($request->id)){
                if($request->hasFile('image')){
                    $path = "public/sponsors";
              
                    $File = $request->file('image');
                    //$t_path = "public/videos/".$request->user_id."/thumb";        
                    $imagename = date('Ymdhis').'_'.$File->getClientOriginalName();
                
                    $filenametostore = $request->file('image')->storeAs($path,$imagename);  
                           
                    Storage::setVisibility($filenametostore, 'public');
                    $fileArray = explode('/',$filenametostore);  
                    
                    $fileName = array_pop($fileArray); 
                    
                }else{
                    $fileName=$request->old_image;
                }
                
            }else{
        
                if($request->hasFile('image')){
                    $path = "public/sponsors";
              
                    $File = $request->file('image');
                    //$t_path = "public/videos/".$request->user_id."/thumb";        
                    $imagename = date('Ymdhis').'_'.$File->getClientOriginalName();
                
                    $filenametostore = $request->file('image')->storeAs($path,$imagename);  
                           
                    Storage::setVisibility($filenametostore, 'public');
                    $fileArray = explode('/',$filenametostore);  
                    
                    $fileName = array_pop($fileArray); 

                }else{
                    redirect( config('app.admin_url').'/sponsors')->with('error','You can\'t leave Image field empty');
                }
            }
               
               
                $postData =array(
                    'image'         => $fileName,
                    'heading'       => $request->heading,
                    'title'         => $request->title,
                    'url'           => $request->url,
                    'active'        => (is_null($request->active)) ? '0' : $request->active,
                                                
                );
                  
                return $postData;
        }
       
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
        DB::table('sponsors')->insert($data);
        return redirect( config('app.admin_url').'/sponsors')->with('success','Sponsor submitted successfully');
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
        $menu='Sponsors';
        $menuUrl=route('admin.sponsors.index');

        $submenu='Edit Sponsor';
        $submenuUrl=route('admin.sponsors.edit',$id);

        $action = 'edit';
 
        $sponsor = DB::table('sponsors')->select(DB::raw("*"))->where('sponsor_id','=',$id)->first();
        return view('admin.sponsors-create',compact('sponsor','id','action','menu','menuUrl','submenu','submenuUrl'));
    }

  
    public function view($id)
    {
        $menu='Sponsors';
        $menuUrl=route('admin.sponsors.index');

        $submenu='View Sponsor';
        $submenuUrl=route('admin.sponsors_view',$id);

        $action = 'view';
        $sponsor = DB::table('sponsors')->select(DB::raw("*"))->where('sponsor_id','=',$id)->first();
    
        return view('admin.sponsors-create',compact('sponsor','id','action','menu','menuUrl','submenu','submenuUrl'));
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
      
        DB::table('sponsors')->where('sponsor_id',$id)->update($data);
        return redirect( config('app.admin_url').'/sponsors')->with('success','Sponsor updated successfully');
    }

  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   
     public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')).'/sponsors/';

        $list = $this->get_datatables($request);
        $data = array();
        $no = $request->start;
        foreach ($list as $sponsor) {
            $no++;
            $row = array();
            // $row[] = '<a class="view" href="'.$currentPath.$sponsor->sponsor_id.'/'.'view"><i class="fa fa-search"></i></a><a class="edit" href="'.$currentPath.$sponsor->sponsor_id.'/edit"><i class="fa fa-edit"></i></a><a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$sponsor->sponsor_id.'"><i class="fa fa-trash"></i></a>';
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$sponsor->sponsor_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $sponsor->heading;
            $row[] = $sponsor->title;
            // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/sponsors/'.$sponsor->image);
            // if($exists){ 
                $img= asset(Storage::url('public/sponsors/').$sponsor->image);
        //    }else{
        //        $img= asset('imgs/no-image.png');
        //    }
            $row[] = '<img width="100" src="'.$img.'">';
            $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.$sponsor->sponsor_id.'/'.'view"><i class="fa fa-search"></i></a> <a class="edit btn btn-warning button-green text-white" href="'.$currentPath.$sponsor->sponsor_id.'/edit"><i class="fa fa-edit"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$sponsor->sponsor_id.'"><i class="fa fa-trash"></i></a>';

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
        $candidateRS = DB::table('sponsors')
                       ->select(DB::raw("*"));
                        
        $strWhere = " sponsor_id!=0 ";
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
        $candidateRS = DB::table('sponsors')->select(DB::raw("count(*) as total"))->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        foreach ($ids as $id) {
            DB::table('sponsors')->where('sponsor_id', $id)->delete();
        }
        
        if($del_error == 'error'){
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error',"rec_exists"=>$rec_exists]);
        }else{
            if( count($ids) > 1){
                $msg = "Sponsor deleted successfully";
            }else{
                $msg = "Sponsor deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success',"rec_exists"=>$rec_exists]);
        }
        return redirect()->back();
    }

    public function copyContent($id)
    {
        $action = 'copy';
        $sponsor = DB::table('sponsors')->select(DB::raw("*"))->where('sponsor_id','=',$id)->first();
        return view('admin.sponsors-create',compact('id','sponsor'));
    }
}
