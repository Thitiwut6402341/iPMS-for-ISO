<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use Illuminate\Validation\Rule;
use App\Http\Libraries\Bcrypt;
use Hamcrest\Description;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class UatController extends Controller
{
    private $jwtUtils;
    private $bcrypt;
    private $db;
    private $mongo;

    public function __construct()
    {
        $this->jwtUtils = new JWTUtils();
        $this->bcrypt = new Bcrypt(10);

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

    private function MongoDBUTCDateTime(int $time)
    {
        try {
            return new UTCDateTime($time);
        } catch (\Exception $e) {
            return null;
        }
    }

    //! [POST]/UAT/create
    public function create(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) {
                return response()->json([
                    "status" => "error",
                    "message" => "Unauthorized",
                    "data" => [],
                ], 401);
            }

            $decoded = $jwt->decoded;

            $rules = [
                'repository_name' => 'required|string|min:1|max:255',
                'project_id' => 'required|string|regex:/^[a-f\d]{24}$/i',
                'description' => 'required|string|min:1|max:255',
                'topics' => 'array',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validator->errors()
                        ]
                    ]
                ], 400);
            }

            $filter = ["_id" => $this->MongoDBObjectId($request->project_id)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id'], "project_name" => 1]];
            $projectDocument = $this->db->selectCollection("Projects")->findOne($filter, $options);

            if (is_null($projectDocument)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Project ID not found",
                    "data" => []
                ], 400);
            }


            $projectID = $projectDocument->project_id;

            $timestamp = $this->MongoDBUTCDateTime(time() * 1000);

            $topics = [];

            if (!is_null($request->topics)) {
                foreach ($request->topics as $info) {
                    array_push($topics, [
                        "uat_code" => "UAT_01",
                        "req_code" => $info['req_code'],
                        "topic" => $info['topic'],
                        "priority" => $info['priority'],
                        "topic_description" => $info['topic_description'],
                        "is_accepted" => $info['is_accepted'],
                        "tester" => $info['tester'],
                    ]);
                }
            }



            $results = $this->db->selectCollection("UAT")->insertOne([
                "repository_name" => $request->repository_name,
                "project_id" => $this->MongoDBObjectId($projectID),
                "description" => $request->description,
                "topics" => $topics,
                "version" => "0.01",
                "is_edit" => null,
                "status" => null,
                "creator_id" => $this->MongoDBObjectId($decoded->creater_by),
                "created_at" => $timestamp,
                "updated_at" => $timestamp,
            ]);

            $id = ((array)$results->getInsertedId())['oid'];
            $responseData = [
                "uat_repo_id" => $id,
                "repository_name" => $request->repository_name,
                "project_id" => $request->project_id,
                "project_name" => $projectDocument->project_name,
                "description" => $request->description,
                "topics" => $topics,
            ];

            return response()->json([
                "status" => "success",
                "message" => "Insert UAT successfully !!",
                "data" => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }


    //! [GET]/UAT/get-UAT
    public function getUAT(Request $request)
    {
        try {
            //! JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) {
                return response()->json([
                    "status" => "error",
                    "message" => "Unauthorized",
                    "data" => [],
                ], 401);
            }

            $pipeline = [
                [
                    '$project' => [
                        'uat_repo_id' => ['$toString' => '$_id'],
                        'repository_name' => 1,
                        'project_id' => ['$toString' => '$project_id'],
                        'creator_id' => ['$toString' => '$creator_id'],
                        'created_at' => [
                            '$dateToString' => [
                                'format' => "%Y-%m-%d %H:%M:%S",
                                'date' => '$created_at'
                            ]
                        ],
                        '_id' => 0,
                    ],
                ],
            ];

            $result = $this->db->selectCollection("UAT")->aggregate($pipeline);

            $data = [];
            foreach ($result as $doc) {
                $data[] = $doc;
            }

            return response()->json([
                "status" => "success",
                "message" => "Get UAT successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //! [PUT]/UAT/edit-UAT
    public function editUAT(Request $request)
    {
        try {
            // Validate JWT
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) {
                return response()->json([
                    "status" => "error",
                    "message" => "Unauthorized",
                    "data" => [],
                ], 401);
            }

            $decoded = $jwt->decoded;

            // Validate request parameters
            $rules = [
                'uat_repo_id' => 'required|string|min:1|max:255',
                'repository_name' => 'required|string|min:1|max:255',
                'project_id' => 'required|string|min:1|max:255',
                'description' => 'required|string|min:1|max:255',
                'topics' => 'required|array',
                'topics.*.req_code' => 'nullable|string',
                'topics.*.priority' => 'nullable|string',
                'topics.*.topic' => 'nullable|string',
                'topics.*.topic_description' => 'nullable|string',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validator->errors()
                        ]
                    ]
                ], 400);
            }

            $timestamp = $this->MongoDBUTCDateTime(time() * 1000);

            // Get UAT ID from the request
            $uat_repo_id = $request->uat_repo_id;

            // Check if UAT ID exists
            $filter = ["_id" => $this->MongoDBObjectId($uat_repo_id)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "uat_repo_id" => 1, "project_id" => 1]];
            $chkUATID = $this->db->selectCollection("UAT")->findOne($filter, $options);

            if (!$chkUATID) {
                return response()->json(["status" => "error", "message" => "UAT ID not found", "data" => []], 404);
            }

            // Retrieve request parameters
            $repository_name = $request->repository_name;
            $project_id = $request->project_id;
            $description = $request->description;
            $topics = $request->topics;
            $updated_at = $timestamp;

            // Create array for UAT cases
            $dataList = [];

            for ($i = 0; $i < count($topics); $i++) {
                $uat_code = "UAT_" . str_pad($i + 1, 3, "0", STR_PAD_LEFT);

                $list = [
                    "uat_code" => $uat_code,
                    "req_code" => $topics[$i]['req_code'] ?? null,
                    "priority" => $topics[$i]['priority'] ?? null,
                    "topic" => $topics[$i]['topic'] ?? null,
                    "topic_description" => $topics[$i]['topic_description'] ?? null,
                ];

                array_push($dataList, $list);
            }

            // Update UAT entry in the database
            $update = [
                '$set' => [
                    "repository_name" => $repository_name,
                    "project_id" => $project_id,
                    "description" => $description,
                    "topics" => $dataList,
                    "updated_at" => $updated_at,
                ]
            ];

            $result = $this->db->selectCollection("UAT")->updateOne($filter, $update);

            if ($result->getModifiedCount() === 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);
            }


            return response()->json([
                "status" => "success",
                "message" => "You edited UAT successfully !!",
                "data" => $result
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], $statusCode);
        }
    }



    //! [DELETE]/UAT/delete-UAT
    public function deleteUAT(Request $request)
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

            $rules = [
                'uat_repo_id'                 => 'required | string | min:1 | max:255',
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

            $uat_repo_id          = $request->uat_repo_id;

            $filter = ["_id" => $this->MongoDBObjectId($uat_repo_id)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "uat_repo_id" => ['$toString' => '$_id'],
                ]
            ];

            $chkLabelID = $this->db->selectCollection("UAT")->find($filter, $options);

            $dataChk = array();
            foreach ($chkLabelID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Test Case ID not found", "data" => []], 500);

            $result = $this->db->selectCollection("UAT")->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "delete UAT failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "delete UAT successfully",
                "data" => []
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"      => [],
            ], $statusCode);
        }
    }
}
