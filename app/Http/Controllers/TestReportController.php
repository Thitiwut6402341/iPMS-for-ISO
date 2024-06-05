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

class TestReportController extends Controller
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

    //* [POST]/test-reports/add-report
    public function createTestReport(Request $request)
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
                'test_case_id'          => 'required|string|min:1|max:255',
                'test_case_code'        => 'required|string|min:1|max:255',
                'is_passed'             => 'required|boolean',
                'actual_result'         => 'required|string',
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


            // //!  Need to pass TestCases
            // $pipline = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$sort'=>['created_at' => 1]],
            //     ['$group' => ['_id' => ['project_id'=>'$project_id'], 'status'=> ['$last' => '$status']]],
            //     ['$project' => [
            //         "_id" => 0,
            //         "status" => 1,
            //     ]]
            // ];
            // $checkStatus = $this->db->selectCollection("TestCases")->aggregate($pipline);
            // $dataCheckStatus = array();
            // foreach ($checkStatus as $doc) \array_push($dataCheckStatus, $doc);

            // if($dataCheckStatus[0]->status !== true){
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'TestCases is not verified',
            //         "data" => [],
            //     ], 400);
            // }

            // //! if documrnt has been created, cannot create again
            // $checkDoc = $this->db->selectCollection("SoftwareComponent")->findOne(['project_id' => $this->MongoDBObjectId($projectID)]);
            // if($checkDoc !== null){
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'This document has been created',
            //         "data" => [],
            //     ], 400);
            // }

            $filter = ["_id" => $this->MongoDBObjectId($request->test_case_id)];

            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0, "test_case_id" => ['$toString' => '$_id'], "repository_name" => 1,
                    "tester_name" => 1, "description" => 1, "creator_id" => ['$toString' => '$creator_id'],
                    "project_id" => ['$toString' => '$project_id'], "version" => 1, "is_edit" => 1, "status" => 1, "topics" => 1
                ]
            ];

            $queryTestcase = $this->db->selectCollection("TestCases")->find($filter, $options);

            $dataTestcase = array();
            foreach ($queryTestcase as $doc) \array_push($dataTestcase, $doc);

            if (\count($dataTestcase) == 0) {
                return response()->json([
                    "status"    => "error",
                    "message"   => "Testcase dosen't exist",
                    "data"      => []
                ], 400);
            }

            $testcaseID  = $request->test_case_id;

            $status = $request->is_passed;
            $actualResult = $request->actual_result;

            // $timestamp = $this->MongoDBUTCDateTime(time() * 1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $results = $this->db->selectCollection("TestReport")->insertOne([
                "test_case_id"          => $this->MongoDBObjectId($testcaseID),
                "creator_id"            => $this->MongoDBObjectId($decoded->creater_by),
                "test_case_code"        => $request->test_case_code,
                "is_passed"             => $status,
                "is_edit"               => null,
                "status"                => null,
                "actual_result"         => $actualResult,
                "created_at"            => $timestamp,
                "updated_at"            => $timestamp,
            ]);

            $id = ((array)$results->getInsertedId())['oid'];

            $responseData = [
                "test_report_id"    => $id,
                "actual_result"     => $actualResult,
                "is_passed"         => $status,
                "test_case_code"    => $request->test_case_code,
            ];

            return response()->json([
                "status"    => "success",
                "message"   => "Insert new test report successfully !!",
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

    //* [POST]/test-reports/edit-testreport
    public function editTestReport(Request $request)
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
                'test_report_id'        => 'required|string|min:1|max:255',
                'test_case_id'          => 'required|string|min:1|max:255',
                'test_case_code'        => 'required|string|min:1|max:255',
                'is_passed'            => 'required|boolean',
                'actual_result'        => 'required|string',
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

            $filter = ["_id" => $this->MongoDBObjectId($request->test_report_id)];

            $options = [
                "limit" => 1,
                "projection" => ["_id" => 0, "test_report_id" => ['$toString' => '$_id'], "test_case_code" => 1, "is_passed" => 1, "actual_result" => 1,]
            ];

            $queryTestcase = $this->db->selectCollection("TestReport")->find($filter, $options);

            $dataTestcase = array();
            foreach ($queryTestcase as $doc) \array_push($dataTestcase, $doc);

            if (\count($dataTestcase) == 0) {
                return response()->json([
                    "status"    => "error",
                    "message"   => "Testcase dosen't exist",
                    "data"      => []
                ], 400);
            }

            $testReportID  = $request->test_report_id;

            $status = $request->is_passed;
            $actualResult = $request->actual_result;

            // $timestamp = $this->MongoDBUTCDateTime(time() * 1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


            $update = [
                "test_case_code"        => $request->test_case_code,
                "creator_id"            => $this->MongoDBObjectId($decoded->creater_by),
                "is_passed"             => $status,
                "actual_result"         => $actualResult,
                "updated_at"            => $timestamp,
            ];

            $results = $this->db->selectCollection("TestReport")->updateOne($filter, ['$set' => $update]);

            return response()->json([
                "status"    => "success",
                "message"   => "Edit test report successfully !!",
                "data"      => [$results],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [DELETE] /test-reports/delete-report
    public function deleteTestReport(Request $request)
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
                'test_report_id'       => ['required',  'string'],
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


            $testreportID      = $request->test_report_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($testreportID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "test_report_id" => ['$toString' => '$_id']]];
            $chkID      = $this->db->selectCollection("TestReport")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "Test report id not found", "data" => []], 500);
            //! check data



            $result = $this->db->selectCollection('TestReport')->deleteOne($filter);

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


    //* [GET] /test-reports/get-list
    public function getListTestReport(Request $request)
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
                ['$project' => ['_id' => 0, 'test_report_id' => ['$toString' => '$_id'], 'creator_id' => ['$toString' => '$creator_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'is_passed' => 1, 'actual_result' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]
            ];


            $result = $this->db->selectCollection('TestReport')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Get all test report in system successfully !!",
                "data"      => $data
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

    //* [GET] /test-reports/get-by-id
    public function getTestReportByID(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "test_report_id" => "required | string | min:1 | max:255",
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $testReportID = $request->test_report_id;

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testReportID)]],
                ['$project' => ['_id' => 0, 'test_report_id' => ['$toString' => '$_id'], 'creator_id' => ['$toString' => '$creator_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'is_passed' => 1, 'actual_result' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]

            ];

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($testReportID)];
            $options = ["projection" => ["_id" => 0, "test_report_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("TestReport")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Testreport id not found", "data" => []], 500);
            //! check data

            $testReportData = $this->db->selectCollection("TestReport")->aggregate($pipline);
            $data = array();
            foreach ($testReportData as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Data test report by id",
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

    //* [GET] /test-reports/get-info-by-id
    public function getTestReportInfoByID(Request $request)
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

            $testCaseID = $request->test_case_id;

            //! Check data
            $filter = ['test_case_id' => $this->MongoDBObjectId($testCaseID)];
            $options = ["projection" => ["_id" => 0, "test_case_id" => ['$toString' => '$test_case_id']]];

            $chkTestcaseID = $this->db->selectCollection("TestReport")->find($filter, $options);
            $dataChk = array();
            foreach ($chkTestcaseID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Test testcase id don't have is TestReport collection", "data" => []], 400);

            //! Check data

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($testCaseID)]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'tester_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'tester_name' => '$name_en', 'user_id' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts_creator', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts_creator', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'TestReport', 'localField' => '_id', 'foreignField' => 'test_case_id', 'as' => 'result_TestReport', 'pipeline' => [['$project' => ['_id' => 0, 'test_report_id' => ['$toString' => '$_id'], 'test_case_id' => ['$toString' => '$test_case_id'], 'test_case_code' => 1, 'is_passed' => 1, 'actual_result' => 1, 'tested_at' => ['$dateToString' => ['date' => '$created_at', 'timezone' => 'Asia/Bangkok', 'format' => '%Y-%m-%d %H:%M:%S']]]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_TestReport', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result2', 'pipeline' => [['$project' => ['_id' => 0, 'project_id' => '$_id', 'project_name' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result2', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'test_report_id' => 1, 'test_case_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'project_name' => 1, 'repository_name' => 1, 'tester_id' => ['$toString' => 'tester_id'], 'tester_name' => 1, 'description' => 1, 'topics' => 1, 'creator_id' => ['$toString' => '$creator_id'], 'creator_name' => 1, 'version' => 1, 'actual_results' => '$result_TestReport', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]
            ];

            $testcaseData = $this->db->selectCollection("TestCases")->aggregate($pipline);
            $data = array();
            foreach ($testcaseData as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Data Info of test report by id",
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
}
