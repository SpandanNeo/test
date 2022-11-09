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
use FFMpeg\Format\Video\X264;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider;
use App\Jobs\ConvertVideoForStreaming;
use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use FFMpeg as FFMpeg1;
use FFProbe as FFProbe1;
use GifCreator\GifCreator;
use FFMpeg\Coordinate\TimeCode;
use Intervention\Image\ImageManagerStatic as Image;
use File;
use Illuminate\Filesystem\Filesystem;

class FlagVideoController extends Controller
{   

     var $column_order = array(null,null,'username', 'title', 'thumb', 'video'); //set column field database for datatable orderable

    var $column_search = array('u.username','v.title','v.video'); //set column field database for datatable searchable

    var $order = array('v.video_id' => 'asc'); // default order

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $menu='Flaged Videos';
        $menuUrl=route('admin.flagvideos.index');
        return view("admin.flagvideos",compact('menu','menuUrl'));
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
    
      private function getCleanFileName($filename){
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.m3u8';
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
        $currentPath = url(config('app.admin_url')).'/videos/';

        $list = $this->get_datatables($request);
        $data = array();
        $no = $request->start;
        foreach ($list as $category) {
            $no++;
            $row = array();
            //<a class="edit" href="'.$currentPath.$category->video_id.'/edit"><i class="fa fa-edit"></i></a>;
            
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$category->video_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $category->username;
            $row[] = $category->title;
            // if(file_exists('storage/videos/'.$category->user_id.'/'.$category->video)){
            // $exists = Storage::disk(env('FILESYSTEM_DRIVER'))->exists('public/videos/'.$category->user_id.'/'.$category->video);
            // if($exists){ 
                $html="<i class='fa fa-play-circle-o video_play' aria-hidden='true'></i>";
            // }else{
            //     $html='';
            // }
            $row[] = "<div style='position:relative;text-align:center;'>".$html."<img src=".asset(Storage::url('public/videos/'.$category->user_id.'/thumb/'.$category->thumb))." height=200 data-bs-toggle='modal' data-bs-target='#homeVideo' class='video_thumb' id='".asset(Storage::url('public/videos/'.$category->user_id.'/'.$category->video))."'/></div>";
            if($category->flag==1){
                $checked="checked";
            }else{
                $checked="";
            }
            $row[] = '<input type="checkbox" class="flaged_toggle" '.$checked.' data-id="'.$category->video_id.'" data-bs-toggle="toggle" data-on="Yes" data-off="No" data-onstyle="success" data-offstyle="danger" >';
            $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.$category->video_id.'/'.'view"><i class="fa fa-search"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$category->video_id.'"><i class="fa fa-trash"></i></a>';
            // $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.$category->cat_id.'/'.'view"><i class="fa fa-search"></i></a> <a class="edit btn btn-warning button-green text-white" href="'.$currentPath.$category->cat_id.'/edit"><i class="fa fa-edit"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$category->cat_id.'"><i class="fa fa-trash"></i></a>';

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
        $candidateRS = DB::table('videos as v')
                        ->leftJoin('users as u' , 'u.user_id','=','v.user_id')
                       ->select(DB::raw("v.*,u.username"));
                        
        $strWhere = " v.deleted=0 and v.flag=1 ";
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
        $candidateRS = DB::table('videos')->select(DB::raw("count(*) as total"))->where('flag',1)->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        foreach ($ids as $id) {
             $videoRes = DB::table('videos')->select(DB::raw("video,user_id"))->where('video_id',$id)->where('flag',1)->first();
            $video_name=explode('/',$videoRes->video);
            $folder_name=$videoRes->user_id.'/'.$video_name[0];
            $f_name=explode('.',$video_name[0]);
            $thumb_name=$videoRes->user_id.'/thumb/'.$f_name[0].'.jpg';
            $gif_name=$videoRes->user_id.'/gif/'.$f_name[0].'.gif';
            
            Storage::deleteDirectory("public/videos/".$folder_name);
            Storage::Delete("public/videos/".$thumb_name);
            Storage::Delete("public/videos/".$gif_name);
            DB::table('videos')->where('video_id', $id)->delete();
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
        $parent_categories = DB::table('categories')
            ->select(DB::raw('cat_id,cat_name,parent_id'))
            ->where('parent_id',0)
            ->orderBy('cat_id','ASC')
            ->get();
        $categories = DB::table('categories')
                ->select(DB::raw('cat_id,cat_name,parent_id'))
                ->where('parent_id','!=',0)
                ->orderBy('cat_id','ASC')
                ->get();
        $sound = DB::table('sounds')->select(DB::raw("*"))->where('sound_id','=',$id)->first();
        return view('admin.sounds-create',compact('id','sound','action','parent_categories','categories'));
    }

    public function flaged_video(Request $request){
        // dd($request->all());
        DB::table('videos')->where('video_id', $request->id)->update(['flag'=>$request->status,'enabled'=>$request->enabled]);
        if($request->status=='1'){
            return 'Video Flaged Successfully';
        }else{
            return 'Video Unflaged';
        }
    }
}
