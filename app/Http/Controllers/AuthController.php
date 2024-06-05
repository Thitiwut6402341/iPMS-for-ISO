<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use Illuminate\Validation\Rule;
use App\Http\Libraries\Bcrypt;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AuthController extends Controller
{

    private $mongo;
    private $db;
    private $jwtUtils;
    private $bcrypt;

    public function __construct()
    {
        $this->bcrypt = new Bcrypt(10);
        $this->jwtUtils = new JWTUtils();

        $this->mongo = new \MongoDB\Client("mongodb://iiot-center2:%24nc.ii0t%402o2E@10.0.0.3:27017/?authSource=admin");
        $this->db = $this->mongo->selectDatabase("iPMS_ISO_DEV");
    }
    private function MongoDBObjectId($id)
    {
        try {
            return new ObjectId($id);
        } catch (\Exception $e) {
            return null;
        }
    }
    private function MongoDBUTCDatetime(int $time)
    {
        try {
            return new UTCDateTime($time);
        } catch (\Exception $e) {
            return null;
        }
    }

    //     private function logUserLogin($userId, $email, $transactionId, $success = true)
    // {
    //     Log::channel('user_logins')->info([
    //         'user_id' => $userId,
    //         'email' => $email,
    //         'transaction_id' => $transactionId,
    //         'success' => $success,
    //         'ip_address' => request()->ip(),
    //         'user_agent' => request()->userAgent(),
    //         'timestamp' => Carbon::now(),
    //     ]);
    // }

    //* /log-in  ? with Email and password
    function login(Request $request)
    {
        try {
            $validators = Validator::make(
                $request->all(),
                [
                    'email' => 'required | string| min:1 | max:100',
                    'password' => 'required | string | min:1 | max:100',
                ]
            );

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            $validated = (object)$validators->validated();
            $emailSystem = strtolower($validated->email);
            $passwordSystem = $validated->password;

            // $transactionId = uniqid();


            $filter = ["email" => $emailSystem];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "creater_by" => ['$toString' => '$_id'],
                    "user_id" => ['$toString' => '$_id'],
                    "name" => 1,
                    "role" => 1,
                    "email" => 1,
                    "password" => 1,
                    "package_level" => 1,
                    "is_actived" => 1,
                    "is_verified" => 1,
                    "verified_at" => 1,
                    "is_account" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                ]
            ];

            $results = $this->db->selectCollection("Users")->find($filter, $options);

            $user = array();
            foreach ($results as $doc) \array_push($user, $doc);

            // return response()->json($user);

            // return response()->json($user);

            $isAccount = $user[0]->is_account;

            // return response()->json($isAccount);

            $chkUser = $this->db->selectCollection("Users")->findOne(["email" => $emailSystem]);

            if ($chkUser == null)
                return response()->json([
                    "status" => "error",
                    "message" => "User does not exist",
                    "data" => []
                ], 401);

            if (!$this->bcrypt->verify($passwordSystem, $chkUser->password))
                return response()->json([
                    "status" => "error",
                    "message" => "Invalid password",
                    "data" => []
                ], 401);

            // return response()->json($user[0]->user_id);


            if ($isAccount !== null) {
                $pipelineAllProject = [
                    ['$project' => ['_id' => 1, 'project_id' => ['$toString' => '$_id'], 'creator_id' => 1, 'customer_name' => 1, 'project_type' => 1, 'project_name' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'result_Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'email' => '$username', 'creator_name' => '$name_en']]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Accounts', 0]], '$$ROOT']]]],
                    ['$project' => ['_id' => 0,  'project_id' => 1,]]
                ];


                $result1 = $this->db->selectCollection('Projects')->aggregate($pipelineAllProject);

                $dataAllProect = array();
                foreach ($result1 as $doc) \array_push($dataAllProect, $doc);

                // return response()->json($dataAllProect);

                $pipelineReponseProject = [
                    ['$match' => ['user_id' => $this->MongoDBObjectId($user[0]->user_id)]],
                    ['$project' => ['_id' => 0, 'account_id' => '$_id', 'user_id' => 1, 'username' => 1, 'name_en' => 1]],
                    ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'account_id', 'foreignField' => 'responsibility.account_id', 'as' => 'project_plan', 'pipeline' => [['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id']]]]]],
                    ['$unwind' => '$project_plan'], ['$group' => ['_id' => ['account_id' => '$account_id', 'user_id' => '$user_id', 'username' => '$username', 'name_en' => '$name_en'], 'project_ids' => ['$addToSet' => '$project_plan.project_id']]],
                    ['$project' => ['_id' => 0, 'account_id' => ['$toString' => '$_id.account_id'], 'user_id' => ['$toString' => '$_id.user_id'],  'name_en' => '$_id.name_en', 'project_ids' => 1]]
                ];

                $result2 = $this->db->selectCollection('Accounts')->aggregate($pipelineReponseProject);

                $data2 = array();
                foreach ($result2 as $doc) \array_push($data2, $doc);

                // return response()->json(($data2));

                if (count($data2) != 0) {
                    $allProjectIds = array_column((array)$dataAllProect, 'project_id');
                    // return response()->json(($allProjectIds));

                    foreach ($data2 as $info) {
                        $permissions = [];

                        foreach ($allProjectIds as $projectId) {
                            $permission = in_array($projectId, (array)$info['project_ids']);
                            $permissions[] = [
                                'project_id' => $projectId,
                                'is_permission' => $permission,
                            ];
                        }
                    }
                } else {
                    $permissions = [];
                }
            } else {
                $permissions = [];
            }


            $role = $user[0]->role;


            \date_default_timezone_set('Asia/Bangkok');
            $dt = new \DateTime();
            $payload = array(
                "creater_by"                => $user[0]->creater_by,
                "user_id"                   => $user[0]->user_id,
                "name"                      => $user[0]->name,
                "email"                     => $user[0]->email,
                "iat"                       => $dt->getTimestamp(),
                "exp"                       => $dt->modify('+ 10hours')->getTimestamp(),
                "role"                      => $role,
                "teamspace_name"            => "99IS-CoDE",
                "creator_name"            => "99IS-CoDE",
                "has_permission_to_project"      => $permissions,

            );

            $token = $this->jwtUtils->generateToken($payload);
            return response()->json([
                "status" => "success",
                "message" => "Login success!!",
                "data" => [
                    [
                        "creater_by"                => $user[0]->creater_by,
                        "user_id"                   => $user[0]->user_id,
                        "name"                      => $user[0]->name,
                        "email"                     => $user[0]->email,
                        "token"                     => $token,
                        "role"                      => $role,
                        "teamspace_name"            => "99IS-CoDE",
                        "creator_name"            => "99IS-CoDE",
                        "has_permission_to_project"      => $permissions,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* /sign-up
    public function signUp(Request $request)
    {
        try {

            $rules = [
                'name' => 'required|string|min:1|max:50',
                'email' => 'required|string|min:1|max:50',
                'password' => 'required|string|min:8|max:50',
                // 'package_level' => ['required', 'string', Rule::in(["free","business","enterprise"])],

            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $now = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $nameSystem = $request->name;
            $emailSystem = strtolower($request->email);
            $passwordSystem = $request->password;
            // $packageLevelSystem = strtolower($request->package_level);

            $filter = ["email" => $emailSystem];

            $result1 = $this->db->selectCollection("Users")->find($filter);

            $chRepeatEmail = array();
            foreach ($result1 as $info) \array_push($chRepeatEmail, $info);

            if (count($chRepeatEmail) !== 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "The email was in the IPMS system already",
                    "data" => [],
                ]);
            }

            $insertData = [
                "name"          => $nameSystem,
                "email"         => $emailSystem,
                "password"      => bcrypt($passwordSystem),
                // "package_level" => $packageLevelSystem,
                "role"          => "user",
                "is_verified"   => null,
                "verified_at"   => null,
                "is_account"     => false,
                "created_at"    => $now,
                "updated_at"    => $now,
            ];

            $result = $this->db->selectCollection('Users')->insertOne($insertData);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Sign up failed",
                    "data" => []
                ], 200);

            return response()->json([
                "status" => "success",
                "message" => "Sign up successfully !",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* /change-password
    public function changePassword(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);


            $rules = [
                'old_password' => 'required | string',
                'new_password' => 'required | string |min:8|max:100',
                'confirm_password' => 'required | string|min:8|max:100'
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            $decoded        = $jwt->decoded;
            $oldPassword    = $request->old_password;
            $newPassword    = $request->new_password;
            $confirmPassword = $request->confirm_password;
            $emailSystem    = $decoded->email;

            $filter = ["email" => $emailSystem];

            $result = $this->db->selectCollection("Users")->find($filter);
            $password = array();
            foreach ($result as $doc) \array_push($password, $doc);

            $isPass  = password_verify($oldPassword, $password[0]->password);
            if (!$isPass) {
                return response()->json([
                    "status" => "error",
                    "message" => "Password for this user incorrect",
                    "data" => []
                ], 401);
            }

            if ($newPassword !== $confirmPassword) {
                return response()->json([
                    "status" => "error",
                    "message" => "password is not match",
                    "data" => []
                ], 400);
            }

            $hash = $this->bcrypt->hash($newPassword);
            $update = ["password" => $hash];
            $filter = ["email" => $emailSystem];
            $result = $this->db->selectCollection("Users")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 200);

            return response()->json([
                "status" => "success",
                "message" => "change password successfully !!",
                "data" => []
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* /forgot-password
    public function checkAuth(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $decoded = $jwt->decoded;

            return response()->json([
                "status" => "error",
                "message" => "The token has not yet expired",
                "data" => []
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
