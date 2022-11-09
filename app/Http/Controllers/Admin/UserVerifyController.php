<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Storage;
use Mail;

class UserVerifyController extends Controller
{   

    var $column_order = array(null,'name', 'address','verified'); //set column field database for datatable orderable

    var $column_search = array('name', 'address','verified'); //set column field database for datatable searchable

    var $order = array('user_id' => 'asc'); // default order

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type=$request->type;

        $menu='User Verifications';
        $menuUrl=route('admin.user-verify.index').'?type='.$type;

        return view("admin.user-verify",compact('type','menu','menuUrl'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $action = 'add';

        $menu='Sounds';
        $menuUrl=route('admin.user-verify.index');

        $submenu='Add Sound';
        $submenuUrl=route('admin.user-verify.create');

        return view('admin.user-verify-create',compact('action','menu','menuUrl','submenu','submenuUrl'));
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
        return redirect( config('app.admin_url').'/user-verify')->with('success','Candidate details submitted successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($active=1)
    {   
        $candidates = DB::table('users')
        ->select(DB::raw('user_id,fname,lname,email,username'))
        ->where('active',$active)
        ->orderBy('user_id','DESC')
        ->get();
        return view("admin.user-verify",compact('candidates','active'));
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
        $action = 'view';
        $candidate = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
       
        return view('admin.candidates-create',compact('candidate','id','action'));
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
        DB::table('users')->where('user_id',$id)->update($data);
        return redirect( config('app.admin_url').'/candidates')->with('success','Candidate details updated successfully');
    }

    public function reject(Request $request)
   {
        DB::table('user_verify')->where('user_verify_id',$request->user_verify_id)->update(array('verified' => 'R','rejected_reason' => $request->reason));
        $msg = "Account Verification Rejected";
    
        $request->session()->put('success', $msg);
        return response()->json(['status' => 'success']);
    }

    public function accept($id)
    {   
        DB::table('user_verify')->where('user_verify_id',$id)->update(array('verified' => 'A'));
        return redirect( config('app.admin_url').'/user-verify')->with('success','Account verification Accepted');
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
        $currentPath = url(config('app.admin_url')).'/user-verify/';

        $list = $this->get_datatables($request);

        $data = array();
        $no = $request->start;
        foreach ($list as $candidates) {
            $no++;
            $row = array();
            // $row[] = '<a class="view" href="'.$currentPath.'view/'.$candidates->user_id.'"><i class="fa fa-search"></i></a><a class="edit" href="'.$currentPath.'edit/'.$candidates->user_id.'"><i class="fa fa-edit"></i></a><a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$candidates->user_id.'"><i class="fa fa-trash"></i></a>';
            $row[] = '<a class="delete deleteSelSingle" style="cursor:pointer;" data-val="'.$candidates->user_verify_id.'"><i class="fa fa-trash"></i></a>';
            $row[] = '<div class="align-center"><input id="cb'.$no.'" name="key_m[]" class="delete_box blue-check" type="checkbox" data-val="'.$candidates->user_verify_id.'"><label for="cb'.$no.'"></label></div>';
            $row[] = $candidates->name;
            $row[] = $candidates->address;
            $row[] = '<img src="'.asset(Storage::url('public/id_proof/'.$candidates->user_id.'/'.$candidates->front_idproof)).'" width="100px" class="img_thumb" data-bs-toggle="modal" data-bs-target="#idProof" id="'.asset(Storage::url('public/id_proof/'.$candidates->user_id.'/'.$candidates->front_idproof)).'">';
            $row[] = '<img src="'.asset(Storage::url('public/id_proof/'.$candidates->user_id.'/'.$candidates->back_idproof)).'" width="100px" class="img_thumb" data-bs-toggle="modal" data-bs-target="#idProof" id="'.asset(Storage::url('public/id_proof/'.$candidates->user_id.'/'.$candidates->back_idproof)).'">';
            // $row[] = $candidates->back_idproof;
        
            if($candidates->verified=='P'){
                $row[] = '<a class="btn btn-danger" href="'.$currentPath.'reject/'.$candidates->user_verify_id.'" onclick="return confirm(\'Are you sure you want to reject ?\')" data-bs-toggle="modal" data-bs-target="#rejectModal" data-whatever="'.$candidates->user_verify_id.'">Reject</a> <a class="btn btn-success" href="'.$currentPath.'accept/'.$candidates->user_verify_id.'?type='.$request->type.'" onclick="return confirm(\'Are you sure you want to accept?\')">Accept</a>';
            }elseif($candidates->verified=='R'){
                $row[] = '<a class="btn btn-success" href="'.$currentPath.'accept/'.$candidates->user_verify_id.'?type='.$request->type.'" onclick="return confirm(\'Are you sure you want to accept ?\')">Accept</a>';
            }else{
                $row[] = '<a class="btn btn-danger" href="'.$currentPath.'reject/'.$candidates->user_verify_id.'" onclick="return confirm(\'Are you sure you want to reject ?\')" data-bs-toggle="modal" data-bs-target="#rejectModal" data-whatever="'.$candidates->user_verify_id.'">Reject</a>';
            }

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
        $candidateRS = DB::table('user_verify')
        ->select(DB::raw("*"));
        
        $strWhere = " verified='".$request->type."'";
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
        return $candidates;
    }

    function count_filtered($request)
    {
        $candidateRS = $this->_get_datatables_query($request);
        return $candidateRS->count();
    }

    public function count_all($request)
    {
        $candidateRS = DB::table('user_verify')->select(DB::raw("count(*) as total"))->where('verified',$request->type)->first();
        return $candidateRS->total;
    }

    public function delete(Request $request){
        $rec_exists = array();
        $del_error = '';
        $ids = explode(',',$request->ids);
        foreach ($ids as $id) {
            DB::table('user_verify')->where('user_verify_id', $id)->delete();
        }
        
        if($del_error == 'error'){
            // $request->session()->put('error',$msg );
            return response()->json(['status' => 'error',"rec_exists"=>$rec_exists]);
        }else{
            if( count($ids) > 1){
                $msg = "Account Verification deleted successfully";
            }else{
                $msg = "Account Verification deleted successfully";
            }
            $request->session()->put('success', $msg);
            return response()->json(['status' => 'success',"rec_exists"=>$rec_exists]);
        }
    }

    public function copyContent($id)
    {
        $action = 'copy';
        $candidate = DB::table('user_verify')->select(DB::raw("*"))->where('user_verify_id','=',$id)->first();
        // dd($category);
        return view('admin.user_verify-create',compact('id','candidate','action'));
    }
}
