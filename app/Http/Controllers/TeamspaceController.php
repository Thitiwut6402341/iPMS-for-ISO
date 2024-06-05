<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use App\Http\Libraries\Bcrypt;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class TeamspaceController extends Controller
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

    //* [GET] team-space/get
    public function getTeamSpace(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $pipeline = [
                ['$project' => [
                    '_id' => 0, 'teamspace_id' => ['$toString' => '$_id'],
                    'creator_id' => ['$toString' => '$creator_id'],
                    'creator_name' => 1,
                    'creator_email' => 1,
                    'teamspace_name' => 1,
                    'description' => 1,
                    'members' => ['$map' => ['input' => '$members', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]],
                    'invite_user' => 1
                ]]
            ];

            $query = $this->db->selectCollection("Teamspaces")->aggregate($pipeline);

            $data = array();
            foreach ($query as $doc) array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "ํYou get all workspace successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] team-space/create
    public function createTeamSpace(Request $request)
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
                'teamspace_name'    => 'required | string',
                'description'       => 'required | array',
                'invite_user'       => 'nullable | array',
                'members'           => 'nullable | array',
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
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);

            $decoded = $jwt->decoded;

            $emailSystem     = $decoded->email;
            $teamSpaceName   = $request->teamspace_name;
            $description     = $request->description;
            $inviteUsers     = $request->invite_user;
            $members         = $request->members;

            $dataMembers = [];
            foreach ($members as $doc) \array_push($dataMembers, $this->MongoDBObjectId($doc));


            $filter2 = ["email" => $emailSystem];
            $option2 = ["limit" => 1, "projection" => ["_id" => 1, "name" => 1, "email" => 1,]];

            $queryID = $this->db->selectCollection("Users")->findOne($filter2, $option2);

            $option3 = ["limit" => 1, "projection" => ["_id" => 0, "teamspace_name" => 1,]];

            $ckRepeatName = $this->db->selectCollection("Teamspaces")->findOne(["teamspace_name" => $teamSpaceName], $option3);

            if ($ckRepeatName !== null) {
                return response()->json([
                    "status" => "error",
                    "message" => "Please create new teamspace name",
                    "data" => []
                ], 400);
            }


            $year2LastDigit = substr(\date('Y'), 2);
            $month = \date('m');
            $monthYear = $month . "/" . $year2LastDigit;

            $filter = ["month_year" => $monthYear];
            $options = ["sort" => ["run_no" => -1], "limit" => 1,];

            $result = $this->db->selectCollection("TeamSpacesRunNo")->find($filter, $options);
            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            $teamSpaceCode = "";
            $runNo = 1;
            if (\count($data) === 0) {
                $teamSpaceCode = \str_pad($runNo, 4, "000", STR_PAD_LEFT);
            } else {
                $info = (object)$data[0];
                $runNo = (int)$info->run_no;
                $runNo += 1;
                $teamSpaceCode = \str_pad($runNo, 4, "000", STR_PAD_LEFT);
            }

            $this->db->selectCollection("TeamSpacesRunNo")->insertOne([
                "main_topic"        => "Teamspace",
                "run_no"            => $runNo,
                "month_year"        => $monthYear,
                "Teamspace_code"    => $teamSpaceCode,
                "run_at"            => $timestamp,
                "run_by_id"         => $this->MongoDBObjectId($decoded->creater_by),
            ]);

            $filter = [
                "creator_id"        => $queryID->_id,
                "creator_email"     => $emailSystem,
                "creator_name"      => $queryID->name,
                "teamspace_name"    => $teamSpaceName,
                "description"       => $description,
                "invite_user"       => $inviteUsers,
                "members"           => $dataMembers,
                "teamspace_code"    => $teamSpaceCode,
                "created_at"        => $timestamp,
                "updated_at"        => $timestamp,
            ];

            $result = $this->db->selectCollection("Teamspaces")->insertOne($filter);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Add teamespace failed",
                    "data" => []
                ], 400);

            return response()->json([
                "status" => "success",
                "message" => "ํYou create new teamespace successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ]);
        }
    }

    //* [PUT] team-space/edit
    public function editTeamSpace(Request $request)
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
                'teamspace_id'      => 'required | string',
                'teamspace_name'    => 'required | string',
                'description'       => 'nullable | array',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "state" => false,
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
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            $decoded = $jwt->decoded;

            $teamSpaceID    = $request->teamspace_id;
            $teamSpaceName  = $request->teamspace_name;
            $description    = $request->description;

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID)];
            $update = [
                "teamspace_name"    => $teamSpaceName,
                "description"       => $description
            ];

            $query = $this->db->selectCollection("Teamspaces")->updateOne($filter, ['$set' => $update]);

            if ($query->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 400);


            return response()->json([
                "status" => "success",
                "message" => "ํYou edit teamespace successfully !!",
                "data" => [$update]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [DELETE] team-space/delete
    public function deleteTeamSpace(Request $request)
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
                'teamspace_id' => 'required | string',
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

            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $decoded = $jwt->decoded;

            $teamSpaceID = $request->teamspace_id;

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID)];

            $query = $this->db->selectCollection("Teamspaces")->deleteOne($filter);

            if ($query->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace id dose not exist",
                    "data" => []
                ], 400);


            return response()->json([
                "status" => "success",
                "message" => "ํYou delete teamspace successfully !!",
                "data" => [$query]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
