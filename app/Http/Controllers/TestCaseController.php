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
use Illuminate\Support\Facades\Mail;
use App\Mail\Validation;

class TestCaseController extends Controller
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
    private function randomName(int $length = 10)
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_';
        $pass = array();
        $alphaLength = \strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < $length; $i++) {
            $n = \rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return \implode($pass);
    }

    //* [POST]/test-cases/new-add-testcase
    public function createTestCase(Request $request)
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
                'repository_name'       => 'required|string|min:1|max:255',
                'project_id'            => 'required|string|regex:/^[a-f\d]{24}$/i',
                'description'           => 'required|string|min:1|max:255',
                'tester_id'             => 'required|string|min:1|max:255',
                'topics'                => 'array',
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

            // return response()->json($request->repository_name);

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
            $testerID = $request->tester_id;

            // $timestamp = $this->MongoDBUTCDateTime(time() * 1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $topics = [];


            // if ($request->has('topics') && is_array($request->topics))
            if (!is_null($request->topics)) {
                foreach ($request->topics as $info) {
                    array_push($topics, [
                        "test_case_code"        => "TC_001",
                        "req_code"              => $info['req_code'],
                        "priority"              => $info['priority'],
                        "topic"                 => $info['topic'],
                        "test_type"             => $info['test_type'],
                        "topic_description"     => $info['topic_description'],
                        "step"                  => $info['step'],
                        "input_data"            => $info['input_data'],
                        "expected_result"       => $info['expected_result'],
                    ]);
                }
            }

            $results = $this->db->selectCollection("TestCases")->insertOne([
                "repository_name"       => $request->repository_name,
                // "tester_name"           => $request->tester_name,
                "project_id"            => $this->MongoDBObjectId($projectID),
                "tester_id"             => $this->MongoDBObjectId($testerID),
                "description"           => $request->description,
                "topics"                => $topics,
                "version"               => "0.01",
                "is_edit"               => true,
                "status"                => null,
                // "is_verified"           => null,
                // "verified_by"           => null,
                // "verified_at"           => null,
                "creator_id"            => $this->MongoDBObjectId($decoded->creater_by),
                "created_at"            => $timestamp,
                "updated_at"            => $timestamp,
            ]);

            $id = ((array)$results->getInsertedId())['oid'];
            $responseData = [
                "test_case_id"      => $id,
                "repository_name"   => $request->repository_name,
                "tester_name"       => $request->tester_name,
                "project_id"        => $request->project_id,
                "project_name"      => $projectDocument->project_name,
                "description"       => $request->description,
                "topics"            => $topics,
            ];

            return response()->json([
                "status"    => "success",
                "message"   => "Insert test case successfully !!",
                "data"      => [$responseData],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [POST] /test-case/combine-project-case
    public function CombineCaseProject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "project_id" => "required | string | min:1 | max:255",
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $projectID = $request->project_id;

            //! check data
            $filter = ["project_id" => $this->MongoDBObjectId($projectID)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$project_id']]];
            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "project id in Planing not found", "data" => []], 500);
            //! check data


            $pipeline = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],

                ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
                ['$unwind' => '$TestCases'],
                ['$group' => [
                    '_id' => '$project_id', 'repository_name' => ['$last' => '$repository_name'], 'tester_id' => ['$last' => '$tester_id'], 'description' => ['$last' => '$description'],
                    'topics' => ['$last' => '$topics'], 'version' => ['$last' => '$version'], 'is_edit' => ['$last' => '$is_edit'], 'status' => ['$last' => '$status'], 'creator_id' => ['$last' => '$creator_id'],
                    'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'responsibility' => ['$last' => '$responsibility'], 'software_requirement' => ['$last' => '$software_requirement'],
                    'TestCases' => ['$last' => '$TestCases']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topics' => '$TestCases.topics']]
            ];

            $projectsPlaning = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);
            $data = array();
            foreach ($projectsPlaning as $doc) \array_push($data, $doc);

            // return response()->json($data[0]);

            $dataConcat = array();
            for ($i = 0; $i < \count($data[0]->software_requirement); $i++) {
                $requirementCode = $data[0]->software_requirement[$i]->req_code;
                $requirementDetails = $data[0]->software_requirement[$i]->req_details;
                $testCaseCode = $data[0]->topics[$i]->test_case_code;
                $title = $data[0]->topics[$i]->topic;
                array_push($dataConcat, [
                    "req_code" => $requirementCode,
                    "req_details" => $requirementDetails,
                    "test_case_code" => $testCaseCode,
                    "topic" => $title
                ]);
            }

            // return response()->json($dataConcat);


            return response()->json([
                "status" => "success",
                "message" => "Data is concatenated",
                "data" => [
                    "project_id" => $data[0]->project_id,
                    "data" => $dataConcat,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [POST] /test-case/get-by-id
    public function getTestcase(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "test_case_id" => "required | string | min:1 | max:255",
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $testcaseID = $request->test_case_id;

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testcaseID)]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'test_case_id' => ['$toString' => '$_id'], 'tester_id' => ['$toString' => '$tester_id'],
                    'tester_name' => '$name_en', 'is_edit' => 1, 'status' => 1, 'repository_name' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'],
                    'description' => 1, 'topics' => 1, 'is_verified' => 1, 'verified_by' => ['$toString' => '$verified_by'], 'verified_at' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]
                ]]
            ];

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($testcaseID)];
            $options = ["projection" => ["_id" => 0, "test_case_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("TestCases")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Testcase id not found", "data" => []], 500);
            //! check data

            $testcaseData = $this->db->selectCollection("TestCases")->aggregate($pipline);
            $data = array();
            foreach ($testcaseData as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Data testcase by id",
                "data"      => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [POST] /test-case/get-info-by-id
    public function getTestcaseInfoByID(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "test_case_id" => "required | string | min:1 | max:255",
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $testcaseID = $request->test_case_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testcaseID)]],
                ['$lookup' => ['from' => 'TestReport', 'localField' => '_id', 'foreignField' => 'test_case_id', 'as' => 'actual_results', 'pipeline' => [['$project' => ['_id' => 0, 'test_report_id' => ['$toString' => '$_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'test_case_code' => 1, 'is_passed' => 1, 'actual_result' => 1, 'tested_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']]]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$actual_results', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result2']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result2', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'tester_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts_creator', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts_creator', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'test_case_id' => ['$toString' => '$_id'], 'test_report_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type' => 1,
                    'repository_name' => 1, 'tester_id' => ['$toString' => '$tester_id'], 'tester_name' => 1, 'description' => 1, 'version' => 1, 'topics' => 1, 'creator_id' => ['$toString' => '$creator_id'], 'creator_name' => 1, 'is_edit' => 1, 'status' => 1,
                    'created_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']]
                ]],
                ['$sort' => ['updated_at' => 1]]
            ];

            $testcaseData = $this->db->selectCollection("TestCases")->aggregate($pipline);
            $data = array();
            foreach ($testcaseData as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Data Info of testcase by id",
                "data"      => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }


    //* [PUT]/test-cases/edit-testcase
    public function editTestCase(Request $request)
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
                'test_case_id' => 'required|string|min:1|max:255',
                'repository_name' => 'required|string|min:1|max:255',
                'project_id' => 'required|string|min:1|max:255',
                'tester_id' => 'required|string|min:1|max:255',
                'description' => 'required|string|min:1|max:255',
                'topics' => 'array',
                'topics.*.req_code' => 'nullable|string',
                'topics.*.priority' => 'nullable|string',
                'topics.*.title' => 'nullable|string',
                'topics.*.description_case' => 'nullable|string',
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

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $testcaseID = $request->test_case_id;

            $filter = ["_id" => $this->MongoDBObjectId($testcaseID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "test_case_id" => 1, "project_id" => 1, 'is_edit' => 1, "status" => 1]];
            $chkUATID = $this->db->selectCollection("TestCases")->findOne($filter, $options);

            if (!$chkUATID) {
                return response()->json(["status" => "error", "message" => "Testcase ID not found", "data" => []], 404);
            }

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testcaseID)]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "creator_id" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                ]]
            ];
            $checkEdit = $this->db->selectCollection("TestCases")->aggregate($pipline);
            $checkEditData = array();
            foreach ($checkEdit as $doc) \array_push($checkEditData, $doc);

            // return response()->json(($checkEditData[0]->version));

            // if there is no documentation in the project
            if (count($checkEditData) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 404);
            }

            // If is_edit is fasle, cannot edit
            if ($checkEditData[0]->is_edit === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot edit this document',
                    "data" => [],
                ], 400);
            }

            // Retrieve request parameters
            $repository_name = $request->repository_name;
            $project_id = $request->project_id;
            $description = $request->description;
            $testerID = $request->tester_id;
            $topics = $request->topics;
            $updated_at = $timestamp;

            // Create array for UAT cases
            $dataList = [];

            for ($i = 0; $i < count($topics); $i++) {
                $test_case_code = "TC_" . str_pad($i + 1, 3, "0", STR_PAD_LEFT);

                $list = [
                    "test_case_code" => $test_case_code,
                    "req_code" => $topics[$i]['req_code'] ?? null,
                    "priority" => $topics[$i]['priority'] ?? null,
                    "topic" => $topics[$i]['topic'] ?? null,
                    "test_type" => $topics[$i]['test_type'] ?? null,
                    "step" => $topics[$i]['step'] ?? null,
                    "input_data" => $topics[$i]['input_data'] ?? null,
                    "expected_result" => $topics[$i]['expected_result'] ?? null,
                    "topic_description" => $topics[$i]['topic_description'] ?? null,
                ];

                array_push($dataList, $list);
            }

            // $update = [
            //     '$set' => [
            //         "repository_name" => $repository_name,
            //         "project_id" => $this->MongoDBObjectId($project_id),
            //         "description" => $description,
            //         "tester_id" => $this->MongoDBObjectId($testerID),
            //         "topics" => $dataList,
            //         "updated_at" => $updated_at,
            //     ]
            // ];
            // return response()->json(($update));


            // if is_edit is not false, can edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status === null) {
                $updateDocument = $this->db->selectCollection("TestCases")->updateOne(
                    ['_id' => $this->MongoDBObjectId($testcaseID)],
                    [
                        '$set' => [
                            "repository_name"       => $repository_name,
                            "project_id"            => $this->MongoDBObjectId($project_id),
                            "description"           => $description,
                            "tester_id"             => $this->MongoDBObjectId($testerID),
                            "topics"                => $dataList,
                            "updated_at"            => $updated_at,
                        ]
                    ]
                );
            }

            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {
                $option = [
                    "project_id"                => $checkEditData[0]->project_id,
                    "creator_id"                => $checkEditData[0]->creator_id,
                    "repository_name"           => $repository_name,
                    "description"               => $description,
                    "tester_id"                 => $this->MongoDBObjectId($testerID),
                    "topics"                    => $dataList,
                    "version"                   => $checkEditData[0]->version . "_edit",
                    "is_edit"                   => true,
                    "status"                    => null,
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("TestCases")->updateOne(
                    ['_id' => $this->MongoDBObjectId($testcaseID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );

                $insertForEditApproved = $this->db->selectCollection("TestCases")->insertOne($option);
            }

            return response()->json([
                "status" => "success",
                "message" => "You Edit testcase successfully !!",
                "data" => []
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

    //* [POST] /test-cases/delete-testcase
    public function deleteTestCase(Request $request)
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

            $rules = [
                'test_case_id'       => ['required',  'string'],            // Rule::in(["Backlog","Unstarted" , "Started" , "Completed","Canceled"])

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

            $testcaseID      = $request->test_case_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($testcaseID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "test_case_id" => ['$toString' => '$_id']]];
            $chkID      = $this->db->selectCollection("TestCases")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "Testcase id not found", "data" => []], 400);
            //! check data


            $result = $this->db->selectCollection('TestCases')->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Delete stats successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //* [GET] /test-cases/get-list
    public function getListTestCase(Request $request)
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


            // $pipeline = [
            //     ['$lookup' => ['from' => 'TestReport', 'localField' => '_id', 'foreignField' => 'test_case_id', 'as' => 'actual_results', 'pipeline' => [['$project' => ['_id' => 0, 'test_report_id' => ['$toString' => '$_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'test_case_code' => 1, 'is_passed' => 1, 'actual_result' => 1, 'tested_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']]]]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$actual_results', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result2']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result2', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'tester_name' => '$name_en']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts_creator', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts_creator', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'test_case_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'project_name' => 1, 'repository_name' => 1, 'is_verified' => 1, 'tester_name' => 1, 'version' => 1, 'creator_name' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']]]],
            //     ['$group' => ['_id' => '$project_id', 'latestVersion' => ['$first' => '$$ROOT']]],
            //     ['$replaceRoot' => ['newRoot' => '$latestVersion']],
            //     ['$sort' => ['created_at' => 1]]
            // ];

            $pipeline = [
                ['$project' => ['_id' => 0, 'test_case_id' => '$_id', 'version' => 1, 'tester_id' => 1, 'repository_name' => 1, 'project_id' => 1, 'is_edit' => 1, 'status' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'test_case_id', 'foreignField' => 'document_id', 'as' => 'Approved', 'pipeline' => [['$project' => ['_id' => 0, 'is_verified' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects', 'pipeline' => [['$project' => ['_id' => 0, 'project_name' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Tester', 'pipeline' => [['$project' => ['_id' => 0, 'name_en' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Tester', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Creator', 'pipeline' => [['$project' => ['_id' => 0, 'name_en' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Creator', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 1, 'test_case_id' => 1, 'project_id' => 1, 'verified_by' => 1, 'verified_at' => 1, 'version' => 1, 'repository_name' => 1, 'project_name' => 1, 'tester_name' => ['$arrayElemAt' => ['$Tester', 0]], 'creator_name' => ['$arrayElemAt' => ['$Creator', 0]], 'is_edit' => 1, 'status' => 1, 'is_verified' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]
                ]],
                ['$group' => ['_id' => '$project_id', 'latestVersion' => ['$first' => '$$ROOT']]],
                ['$replaceRoot' => ['newRoot' => '$latestVersion']],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'is_verified' => 1, 'verified_by' => 1, 'verified_at' => 1, 'version' => 1, 'repository_name' => 1, 'project_name' => 1,  'created_at' => 1, 'is_edit' => 1, 'status' => 1, 'updated_at' => 1, 'tester_name' => '$tester_name.name_en', 'creator_name' => '$creator_name.name_en']],
                ['$sort' => ['created_at' => 1]]
            ];


            $result = $this->db->selectCollection('TestCases')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all testcase in system successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //* [GET] /test-cases/get-repositories
    public function getTestCaseRepositories(Request $request)
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


            $pipeline = [
                ['$project' => ['_id' => 0, 'test_case_id' => '$_id', 'version' => 1, 'is_edit' => 1, 'status' => 1, 'tester_id' => 1, 'repository_name' => 1, 'project_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'test_case_id', 'foreignField' => 'document_id', 'as' => 'Approved', 'pipeline' => [['$project' => ['_id' => 0, 'is_verified' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects', 'pipeline' => [['$project' => ['_id' => 0, 'project_name' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Tester', 'pipeline' => [['$project' => ['_id' => 0, 'name_en' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Tester', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Creator', 'pipeline' => [['$project' => ['_id' => 0, 'name_en' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Creator', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'is_edit' => 1, 'status' => 1, 'version' => 1, 'test_case_id' => ['$toString' => '$test_case_id'],  'is_verified' => 1, 'verified_by' => 1, 'verified_at' => 1, 'repository_name' => 1, 'project_name' => 1, "tester_id" => ['$toString' => '$tester_id'],
                    'tester_name' => ['$arrayElemAt' => ['$Tester', 0]], 'creator_name' => ['$arrayElemAt' => ['$Creator', 0]], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]
                ]],
                ['$project' => [
                    '_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'is_edit' => 1, 'status' => 1, 'version' => 1, 'test_case_id' => ['$toString' => '$test_case_id'],  'is_verified' => 1, 'verified_by' => 1, 'verified_at' => 1, 'repository_name' => 1, 'project_name' => 1, "tester_id" => 1,
                    'tester_name' => '$tester_name.name_en', 'creator_name' => '$creator_name.name_en', 'created_at' => 1
                ]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'is_edit' => ['$last' => '$is_edit'], 'status' => ['$last' => '$status'], 'repository_name' => ['$last' => '$repository_name'], 'is_verified' => ['$last' => '$is_verfified'], 'version' => ['$last' => '$version'],
                    'project_id' => ['$last' => '$project_id'], 'test_case_id' => ['$last' => '$test_case_id'],
                    'tester_name' => ['$last' => '$tester_name'], 'creator_name' => ['$last' => '$creator_name'], 'created_at' => ['$last' => '$created_at'], "tester_id" => ['$last' => '$tester_id']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'test_case_id' => 1, 'is_edit' => 1, 'status' => 1, 'repository_name' => 1, 'tester_name' => 1, 'creator_name' => 1, 'created_at' => 1, 'version' => 1, 'is_verified' => 1, "tester_id" => 1,]]
            ];

            $result = $this->db->selectCollection('TestCases')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all testcase in system successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //! [POST] /test-cases/get-test-cases-detail
    public function GetTestCaseDetails(Request $request)
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

            $validator = Validator::make($request->all(), [
                'test_case_id'       => 'required | string | min:1 | max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $testcaseID = $request->test_case_id;


            $pipeline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testcaseID)]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork']], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creator_user', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en']]]]], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creator_user', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'verified_by_user', 'pipeline' => [['$project' => ['_id' => 0, 'verified_user' => '$name_en']]]]], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verified_by_user', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'creator_user', 'pipeline' => [['$project' => ['_id' => 1, 'tester_name' => '$name_en']]]]], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creator_user', 0]], '$$ROOT']]]], ['$project' => ['_id' => 0, 'test_case_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'project_name' => 1, 'project_type' => 1, 'is_edit' => 1, 'status' => 1, 'customer_name' => 1, 'repository_name' => 1, 'description' => 1, 'tester_name' => '$tester_name', 'topics' => 1, 'version' => 1, 'creator_name' => '$creator_name', 'verified_by' => '$verified_user', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]
            ];

            $userDoc = $this->db->selectCollection("TestCases")->aggregate($pipeline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);

            if (\count($dataUserDoc) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 400);

            $projectID = $dataUserDoc[0]->project_id;

            $cover = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'creator_id', 'foreignField' => '_id', 'as' => 'creator_user', 'pipeline' =>
                [
                    ['$project' => ['_id' => 0, 'creator' => ['$toString' => '$name']]]
                ]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creator_user', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statement_of_work_id', 'pipeline' => [['$project' => ['_id' => 0]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$statement_of_work_id', 0]], '$$ROOT']]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$customer_contact', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'approve_doc', 'pipeline' => [['$project' => ['_id' => 0]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$approve_doc', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'creator_id', 'foreignField' => '_id', 'as' => 'verified_by_user', 'pipeline' => [['$project' => ['_id' => 0, 'verified_user' => ['$toString' => '$name']]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verified_by_user', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'verification_type_doc']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verification_type_doc', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0,
                    'project_id' => ['$toString' => '$project_id'],
                    'version' => 1,
                    'conductor' => '$creator',
                    'approver' => '$verified_user',
                    'reviewer' => '$name',
                    'created_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'verified_at' => ['$dateToString' => ['date' => '$verified_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'validated_at' => ['$dateToString' => ['date' => '$validated_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'approver_number' => 1,
                    'verification_type' => 1
                ]]
            ];

            $userCov = $this->db->selectCollection("TestCases")->aggregate($cover);
            // $userCov = $this->db->selectCollection("TestCases")->find(['project_id' => $this->MongoDBObjectId($projectID)]);
            $dataCover = array();

            foreach ($userCov as $cov) {
                // return response()->json($cov->version);
                if (str_ends_with((string)$cov->version, '.00')) {
                    for ($i = 0; $i < 2; $i++) {
                        if ($i == 0) {
                            $verified_at = $cov->verified_at;
                            $validated_at = null;
                            $approver = $cov->approver;
                            $reviewer = null;
                        } else {
                            $verified_at = null;
                            $validated_at = $cov->validated_at;
                            $approver = null;
                            $reviewer = $cov->reviewer;
                        }
                        $coverData = [
                            "project_id" => $cov->project_id,
                            "version" => $cov->version,
                            "conductor" => null,
                            "approver" => ["approver" => $approver, "verified_at" => $verified_at],
                            "reviewer" => ['reviewer' => $reviewer, 'validated_at' => $validated_at],
                            "description" => "Approved",
                            "created_at" => null,
                            // "verified_at" =>  $verified_at,
                            // "validated_at" => $validated_at,
                        ];
                        array_push($dataCover, $coverData);
                    }
                } else if ((string)$cov->version == '0.01') {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "reviewer" => ['reviewer' => null, 'validated_at' => null],
                        "description" => "Created",
                        "created_at" => $cov->created_at,
                        // "verified_at" => null,
                        // "validated_at" => null,
                    ];
                    array_push($dataCover, $coverData);
                } else {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "reviewer" => ['reviewer' => null, 'validated_at' => null],
                        "description" => "Edited",
                        "created_at" => $cov->created_at,
                        // "verified_at" => null,
                        // "validated_at" => null,
                    ];
                    array_push($dataCover, $coverData);
                }
                // return response()->json($dataCover);
            };
            return response()->json([
                'status' => 'success',
                'message' => 'Get Testcase details successfully !!',
                "data" => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $dataUserDoc,

                ],
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
