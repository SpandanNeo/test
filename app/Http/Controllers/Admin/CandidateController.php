<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 
use Mail;

class CandidateController extends Controller
{   

    var $column_order = array(null,'username', 'fname','lname','email'); //set column field database for datatable orderable

    var $column_search = array('username','fname','lname','email'); //set column field database for datatable searchable

    var $order = array('user_id' => 'asc'); // default order

    public function __construct() {
        $this->middleware('app_version_check', ['only' => ['inactive','active','delete']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($active=1)
    {
        $menu='Users';
        $menuUrl=route('admin.candidates.index');
// dd($menuUrl);
        return view("admin.candidates",compact('active','menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $menu='Users';
        $menuUrl=route('admin.candidates.index');

        $submenu='Add Category';
        $submenuUrl=route('admin.candidates.create');

        $action = 'add';
        return view('admin.candidates-create',compact('action','menu','menuUrl','submenu','submenuUrl'));
    }

    private function _form_validation($request){
        $rules = [
            'username'  => 'required',
            'fname'  => 'required',
            'lname'   => 'required',
            'email'       => 'required',
           
        ];
        $messages = [
            'username.required' => 'You can\'t leave User Name field empty',
            'f_name.required' => 'You can\'t leave First Name field empty',
            'l_name.required'    => 'You can\'t leave Last Name field empty',
            'email.required'    => 'You can\'t leave Email field empty'
        ]; 

        $this->validate($request,$rules,$messages);
        $postData = array(
            'username'=> $request->username,
            'fname'=> $request->fname,
            'lname' => $request->lname,
            'email'     => $request->email,
            'active'    => $request->active,
            
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
        DB::table('users')->insert($data);
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate details submitted successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($active=1)
    {   
        $menu='Users';
        $menuUrl=route('admin.candidates.show',$active); 

        $candidates = DB::table('users')
        ->select(DB::raw('user_id,fname,lname,email,username'))
        ->where('active',$active)
        ->orderBy('user_id','DESC')
        ->get();
        return view("admin.candidates",compact('candidates','active','menu','menuUrl'));
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
        $candidate = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
       //dd($candidate);
        return view('admin.candidates-create',compact('candidate','id','action'));
    }

    public function view($id)
    {

        $menu='Users';
        $menuUrl=route('admin.candidates.show',1);

        $submenu='View User';
        $submenuUrl=route('admin.candidates_view',$id);

        $action = 'view';
        $candidate = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
       
        return view('admin.candidates-create',compact('candidate','id','action','menu','menuUrl','submenu','submenuUrl'));
    }


     public function loadMoreVideos(Request $request)
    {  
        $offset=$request->input('offset');
        $id=$request->input('id');
        $action = 'view';
        //$videoUpload=env('AWS_URL')."videos/".$id."/";
        $videoUpload=url(config('app.video_path'))."/".$id."/";
        $videos = DB::table('videos')->select(DB::raw("*"))->where('user_id','=',$id)->where('enabled','=','1')->orderBy('created_at','desc')->offset($offset)->limit(config('app.video_limit'))->get();
       
        $loaded_videos=$request->loaded_videos + count($videos);
        $html='';
        foreach($videos as $video) {  
            $html.= '<div class="col-md-4 inner_video">';
            $html.= '<div class="video_div">';
            $html.=   '<video width="100%" height="100%" controls>';
            $html.=  '<source src="'.$videoUpload.$video->video.'" type="video/mp4">';
            $html.=  '</video>';
                // echo '<div class="title_heading">'.$video->title.'</div>';
            $html.= '</div>';
            $html.= '</div>';
            }
            $json=array('status'=>'success','html_data'=>$html,'loaded_videos'=>$loaded_videos);
            echo json_encode($json);
    }


    public function videos($action,$id)
    {
        $videos_total_count = DB::table('videos')->select(DB::raw("*"))->where('user_id','=',$id)->where('enabled','=',1)->count();
        $videos = DB::table('videos')->select(DB::raw("*"))->where('user_id','=',$id)->where('enabled','=',1)->orderBy('created_at','desc')->limit(config('app.video_limit'))->get();
         //dd($videos);
         $loaded_videos=count($videos);
        return view('admin.candidates-videos',compact('videos','id','action','videos_total_count','loaded_videos'));
    }

    // public function audios($action,$id)
    // {
    //     $audios_total_count = DB::table('user_audios')->select(DB::raw("*"))->where('user_id','=',$id)->count();
    //     $audios = DB::table('user_audios')->select(DB::raw("*"))->where('user_id','=',$id)->limit(env('VIDEO_LIMIT'))->get();
    //      //dd($candidate);
    //     return view('admin.candidates-audios',compact('audios','id','action','audios_total_count'));
    // }



    
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
        DB::table('users')->where('user_id',$id)->update($data);
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate details updated successfully');
    }

    public function inactive($id)
    {   
        DB::table('users')->where('user_id',$id)->update(array('active' => '0'));
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate Inactivated');
    }

    public function active($id)
    {   
        //$users = DB::table('users')->select(DB::raw("username,email"))->where('user_id','=',$id)->first();
        // $mailBody ='<b>Dear,'.$users->username.'<b><br /> <br />
        // We have reviewed your account. We are happy to announce that your account is active now. You can login in your account and post your Pictures, Videos, Audios. Your profile is now searchable by producers waiting to hire new candidates.<br /><br />
        // BEST OF LUCK WITH YOUR DREAMS<br />< br/>
        // Thanks <br />'.env('COMPANY_NAME');
        // $array = array('subject'=>'Hurrey! You account is active at Films Dream','view'=>'emails.email','body' => $mailBody);
        //    Mail::to($users->email)->send(new SendMail($array)); 
        DB::table('users')->where('user_id',$id)->update(array('active' => '1'));
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate Activated');
    }

    public function changePassword($id)
    {   
        $action="changePassword";
        $users = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
         //dd($candidate);
        return view('admin.candidates-changePassword',compact('user','id'));
    }

    public function updatePassword(Request $request,$id)
    {   
        $rules = [
            'password' => 'required',
            'confirm_password' => 'required|same:password',

            
        ];
        $messages = [
            'password.required' => 'You can\'t leave Password field empty',
            'confirm_password.required'    => 'You can\'t leave Confirm Password field empty',
           
        ]; 
        $this->validate($request,$rules,$messages);
        
        $password=Hash::make($request->password);
       
        DB::table('users')->where('user_id',$id)->update(array('password' => $password));
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate Password updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function serverProcessing(Request $request)
    {
        $currentPath = url(config('app.admin_url')).'/candidates/';

        $list = $this->get_datatables($request);

        $data = array();
        $no = $request->start;
        foreach ($list as $candidates) {
            $no++;
            $row = array();
            // $row[] = '<a class="view" href="'.$currentPath.'view/'.$candidates->user_id.'"><i class="fa fa-search"></i></a><a class="edit" href="'.$currentPath.'edit/'.$candidates->user_id.'"><i class="fa fa-edit"></i></a><a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$candidates->user_id.'"><i class="fa fa-trash"></i></a>';
            // $row[] = '<a class="view" href="'.$currentPath.'view/'.$candidates->user_id.'"><i class="fa fa-search"></i></a> <a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$candidates->user_id.'"><i class="fa fa-trash"></i></a>';
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$candidates->user_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $candidates->username;
            $row[] = $candidates->fname;
            $row[] = $candidates->lname;
            $row[] = $candidates->email;
        
            if($candidates->active==1){
                $row[] = '<a class="btn btn-danger" href="'.$currentPath.'inactive/'.$candidates->user_id.'" onclick="return confirm(\'Are you sure ?\')">Inactive</a>';
            }else{
                $row[] = '<a class="btn btn-success" href="'.$currentPath.'active/'.$candidates->user_id.'" onclick="return confirm(\'Are you sure ?\')">Active</a>';
            }
            $row[] = '<a class="view btn btn-success green-bg text-white" href="'.$currentPath.'view/'.$candidates->user_id.'"><i class="fa fa-search"></i></a> <a class="delete deleteSelSingle btn btn-danger text-white" style="cursor:pointer;" data-val="'.$candidates->user_id.'"><i class="fa fa-trash"></i></a>';

            //$row[] = '<a class="btn btn-success" href="'.$currentPath.'active/'.$candidates->user_id.'" onclick="return confirm(\'Are you sure ?\')">Active</a> <a href="'.$currentPath.'changePassword/'.$candidates->user_id.'" class="btn btn-primary">Change Password</a>';
           // $row[] = '<a href="#" data-bs-toggle="modal" data-bs-target="#modal1" class="btn btn-primary process" data-val="'.$candidates->user_id.'">Visible</a>';
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
        $candidateRS = DB::table('users')
        ->select(DB::raw("*"));
        
        $strWhere = " active='".$request->active."'";
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
            $candidateRS = $candidateRS->whereRaw(DB::raw($strWhere ));
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
        $candidateRS = DB::table('users')->select(DB::raw("count(*) as total"))->where('active',$request->active)->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        // dd($ids);
        foreach ($ids as $id) {
            DB::table('users')->where('user_id', $id)->delete();
            DB::table('follow')->whereRaw(DB::raw('follow_by='. $id.' or follow_to='.$id))->delete();
            
            
        }
        
        if($del_error == 'error'){
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error',"rec_exists"=>$rec_exists]);
        }else{
            if( count($ids) > 1){
                $msg = "Candidate deleted successfully";
            }else{
                $msg = "Candidate deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success',"rec_exists"=>$rec_exists]);
        }
    }

    public function copyContent($id)
    {
        $action = 'copy';
        $candidate = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
        // dd($category);
        return view('admin.candidates-create',compact('id','candidate','action'));
    }

    public function active_user(Request $request){
        DB::table('users')->where('user_id', $request->id)->update(['active'=>$request->active]);
        if($request->active=='1'){
            return 'User Activated Successfully';
        }else{
            return 'User Inactivated Successfully!';
        }
    }

}
