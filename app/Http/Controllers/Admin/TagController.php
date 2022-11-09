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


class TagController extends Controller
{   

     var $column_order = array(null,'tag','banner'); //set column field database for datatable orderable

    var $column_search = array('tag','banner'); //set column field database for datatable searchable

    var $order = array('tag_id' => 'asc'); // default order

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
        $menu='Tags';
        $menuUrl=route('admin.tags.index');
        return view("admin.tags",compact('menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $action = 'add';    
        $menu='Tags';
        $menuUrl=route('admin.tags.index');

        $submenu='Add Tag';
        $submenuUrl=route('admin.tags.create');

        return view('admin.tags-create',compact('action','menu','menuUrl','submenu','submenuUrl'));
    }

    private function _form_validation($request){
      
        $rules = [
            'tag'        => 'required',
            'banner'          => 'image|mimes:jpeg,png,jpg,gif,svg',  
            
        ];
        $messages = [
            'tag.required'   => 'Tag is required',
            'banner.required'		  	=> 'banner is required',   
            
        ]; 
        $this->validate($request,$rules,$messages);

               
            $functions = new Functions();
            if($request->id>0 || isset($request->id)){
                if($request->hasFile('banner')){
                    $path = "public/banners";
              
                    $Filebanner = $request->file('banner');
                    //$t_path = "public/videos/".$request->user_id."/thumb";        
                    $bannername = date('Ymdhis').'_'.$Filebanner->getClientOriginalName();
                
                    $filenametostore = $request->file('banner')->storeAs($path,$bannername);  
                           
                    Storage::setVisibility($filenametostore, 'public');
                    $fileArray = explode('/',$filenametostore);  
                    
                    $fileName = array_pop($fileArray); 
                    
                }else{
                    $fileName=$request->old_banner;
                }
                
            }else{
        
                if($request->hasFile('banner')){
                    $path = "public/banners";
              
                    $Filebanner = $request->file('banner');
                    //$t_path = "public/videos/".$request->user_id."/thumb";        
                    $bannername = date('Ymdhis').'_'.$Filebanner->getClientOriginalName();
                
                    $filenametostore = $request->file('banner')->storeAs($path,$bannername);  
                           
                    Storage::setVisibility($filenametostore, 'public');
                    $fileArray = explode('/',$filenametostore);  
                    
                    $fileName = array_pop($fileArray); 

                }else{
                    redirect( config('app.admin_url').'/tags')->with('error','You can\'t leave Tag field empty');
                }
            }
               
               if($request->tag!=""){
                   $first_char=substr($request->tag,0,1);
                   if($first_char=='#'){
                       $tag=$request->tag;
                   }else{
                       $tag='#'.$request->tag;
                   }
               }

                if($request->title==''){
						$title='';
					}else{
						$title=$request->title;
					}
                $postData =array(
                    'banner'      => $fileName,
                    'tag'      => $tag,
                                                
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

        DB::table('video_tags')->insert($data);
        return redirect( config('app.admin_url').'/tags')->with('success','Tag submitted successfully');
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
        $menu='Tags';
        $menuUrl=route('admin.tags.index');

        $submenu='Edit Tag';
        $submenuUrl=route('admin.tags.edit',$id);

        $tag = DB::table('video_tags')->select(DB::raw("*"))->where('tag_id','=',$id)->first();
        return view('admin.tags-create',compact('tag','id','action','menu','menuUrl','submenu','submenuUrl'));
    }

  
    public function view($id)
    {
        $action = 'view';
        $menu='Tags';
        $menuUrl=route('admin.tags.index');

        $submenu='View Tag';
        $submenuUrl=route('admin.tags_view',$id);

        $tag = DB::table('video_tags')->select(DB::raw("*"))->where('tag_id','=',$id)->first();
    
        return view('admin.tags-create',compact('tag','id','action','menu','menuUrl','submenu','submenuUrl'));
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
      
        DB::table('video_tags')->where('tag_id',$id)->update($data);
        return redirect( config('app.admin_url').'/tags')->with('success','Tag updated successfully');
    }

  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   
     public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')).'/tags/';

        $list = $this->get_datatables($request);
        $data = array();
        $no = $request->start;
        foreach ($list as $category) {
            $no++;
            $row = array();
            // $row[] = '<a class="view" href="'.$currentPath.$category->tag_id.'/'.'view"><i class="fa fa-search"></i></a><a class="edit" href="'.$currentPath.$category->tag_id.'/edit"><i class="fa fa-edit"></i></a><a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$category->tag_id.'"><i class="fa fa-trash"></i></a>';
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$category->tag_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $category->tag;
            $row[] = '<img width="100" src="'.asset(Storage::url('public/banners/'.$category->banner)).'">';
            $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.$category->tag_id.'/'.'view"><i class="fa fa-search"></i></a> <a class="edit btn btn-warning button-green text-white" href="'.$currentPath.$category->tag_id.'/edit"><i class="fa fa-edit"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$category->tag_id.'"><i class="fa fa-trash"></i></a>';

            
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
        $candidateRS = DB::table('video_tags')
                       ->select(DB::raw("*"));
                        
        $strWhere = " tag_id!=0 ";
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
        $candidateRS = DB::table('video_tags')->select(DB::raw("count(*) as total"))->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        foreach ($ids as $id) {
            DB::table('video_tags')->where('tag_id', $id)->delete();
        }
        
        if($del_error == 'error'){
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error',"rec_exists"=>$rec_exists]);
        }else{
            if( count($ids) > 1){
                $msg = "Video deleted successfully";
            }else{
                $msg = "Video deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success',"rec_exists"=>$rec_exists]);
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
        $tag = DB::table('video_tags')->select(DB::raw("*"))->where('tag_id','=',$id)->first();
        return view('admin.tags-create',compact('id','tag'));
    }
}
