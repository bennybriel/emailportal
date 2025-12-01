<?php

namespace App\Http\Controllers;

use App\Services\GoogleUserService;
use Illuminate\Http\Request;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserinfoRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EmailApiController extends Controller
{
    private GoogleUserService $google;

    public function __construct(GoogleUserService $google)
    {
        $this->google = $google;
    }

    //create user email address
    public function create(CreateUserRequest $request)
    {
        $validated = $request->validated();

        $result = $this->google->createUser($validated);

        return response()->json($result);
    }

    //reset password by email
    public function resetPassword(Request $request)
    {
          $validator = Validator::make($request->all(), [
           'email' => 'required|email', 'password' => 'required|string'
        ]);


          if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 422,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
        }

        return response()->json(
            $this->google->resetPassword($request->email,$request->password)
        );
    }

    //get user info by email
    public function getUser(Request $request)
    {
         $validator = Validator::make($request->all(), [
           'email' => 'required|email'
        ]);


          if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 422,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
        }

        return response()->json(
            $this->google->getUser($request->email)
        );
    }
  
    //Get user info by matricno
     public function getinfo(Request $request)
     {
       $validator = Validator::make($request->all(), [
           'matricno' => 'required'
        ]);


          if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 422,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
        }

        return response()->json(
            $this->google->getUserByMatricno($request->matricno)
        );
    }

   //update user info by email
    public function updateUserinfo(UpdateUserinfoRequest $request)
    {
        //$request->validate(['email' => 'required|email', 'matricno'=> 'required','programme'=> 'required','session'=> 'required']);
        $validated = $request->validated();
        return response()->json(
            $this->google->addMatricnoToUser($request->email,$request->matricno,$request->programme, $request->session)
        );
    }


    //  public function updateUserinfoAll()
    //  {
    //    $programme ="Undergraduate";
    //    $user = DB::table('users')->where('isupdated',0)
    //                              ->where('activesession','2024/2025')
    //                              ->where('apptype','UGD')
    //                              ->where('schoolemail','<>', NULL)
    //                             ->limit(10)
    //                             ->get();
    //    dd($user);
    //     foreach($user as $user)
    //     {
    //          $this->google->addMatricnoToUser($user->schoolemail,$user->matric,$programme, $user->activesession);
    //          DB::table('users')->where('schoolemail',$user->schoolemail)->update(['isupdated'=>1]);
    //     }

    // }

public function updateUserinfoAll()
{
    $programme = "Undergraduate";

    // Fetch only 20 users to process
    $users = DB::table('users')
        ->where('isupdated', 0)
        ->where('activesession', '2024/2025')
        ->where('apptype', 'UGD')
        ->whereNotNull('schoolemail')
        ->whereNotNull('matric')
        ->orderBy('id')
        ->limit(1000)
        ->get();
    //dd($users);
    foreach ($users as $item) {
        try {
            // Update Google account
            $this->google->addMatricnoToUser(
                $item->schoolemail,
                $item->matric,
                $programme,
                $item->activesession
            );

            // Mark as updated
            DB::table('users')
                ->where('id', $item->id)
                ->update(['isupdated' => 1]);

        } catch (\Exception $e) {
            Log::error("Failed updating {$item->schoolemail}: " . $e->getMessage());
        }
    }

    return response()->json(['processed' => count($users)]);
}




    public function deleteGoogleAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'email' => 'required|email'
        ]);


          if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 422,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
        }


        $email = $request->email;

        $result = $this->google->deleteGoogleUser($email);

        return response()->json($result);
    }

public function getTotalUsers()
{
    try {
        $total = $this->google->getTotalUsers(); // assuming this returns an integer

        return response()->json([
            'success' => true,
            'total_users' => $total
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error_code' => 500,
            'message' => 'Unable to fetch total users: ' . $e->getMessage()
        ], 500);
    }
}

public function getTotalUsersBySession(Request $request, $session)
{
    try {
        $total = $this->google->getTotalUsersBySession($session);

        return response()->json([
            'success' => true,
            'session' => $session,
            'total_users' => $total
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error_code' => 500,
            'message' => 'Unable to fetch total users by session: ' . $e->getMessage()
        ], 500);
    }
}

public function getTotalUsersByProgramme(Request $request, $programme)
{
    try {
        $total = $this->google->getTotalUsersByProgramme($programme);

        return response()->json([
            'success' => true,
            'programme' => $programme,
            'total_users' => $total
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error_code' => 500,
            'message' => 'Unable to fetch total users by programme: ' . $e->getMessage()
        ], 500);
    }
}


}
