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
use Illuminate\Support\Facades\DB;

class TraceabilityController extends Controller
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

    //* [POST] /traceability/new
    public function addTraceability(Request $request)
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
                'project_id'             => 'required | string | min:1 | max:50',
                'traceability_data'      => 'required | array',
                // 'test_case_id'           => 'nullable | string | min:1 | max:50',
                // "software_design"        => 'nullable | array',
                // "software_component"     => 'nullable | array',
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

            //! Check data
            $filter = ["project_id" => $this->MongoDBObjectId($request->project_id)];
            $options = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$project_id']]];

            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project in planing collection not found", "data" => []], 400);

            // $filter2 = ["_id" => $this->MongoDBObjectId($request -> test_case_id)];
            // $options2 = ["projection" =>["_id"=>0,"test_case_id" => ['$toString' => '$_id'],"requirement_code"=>1,"test_case_code"=>1]];

            // $chkTestCaseID = $this->db->selectCollection("TestCases")->find($filter2,$options2);
            // $testcaseID        = $this->db->selectCollection("TestCases")->findOne($filter2,$options2)->test_case_id;

            // $dataChk = array();
            // foreach ($chkTestCaseID as $doc) \array_push($dataChk, $doc);
            // if (\count($dataChk) == 0)
            // return response()->json(["status" => "error", "message" => "Testcase ID  not found" , "data"=> []],500);
            //! Check data

            $projectID          = $request->project_id;
            $traceabilityData   = $request->traceability_data;

            //! Old method
            // $testcaseCode      = $this->db->selectCollection("TestCases")->findOne($filter2,$options2)->test_case_code;
            // $softwareReqID     = $this->db->selectCollection("TestCases")->findOne($filter2,$options2)->requirement_code;

            // $softwareDesign          = $request -> software_design;
            // $dataSoftwareDesign = [];
            // foreach ($softwareDesign as $doc) \array_push($dataSoftwareDesign,$doc);

            // $softwareComponent       = $request -> software_component;
            // $dataSoftwareComponent = [];
            // foreach ($softwareComponent as $doc) \array_push($dataSoftwareComponent,$doc);

            // $dataList = [];

            // for ( $i = 0; $i < count($dataSoftwareDesign); $i++ ){
            //     $list = [
            //         "requirement_code"        => $softwareReqID,
            //         "software_design"       => $dataSoftwareDesign[$i] ,
            //         "software_component"    => $dataSoftwareComponent[$i],
            //         "test_case_id"        => $testcaseID,
            // ];
            //     array_push($dataList,$list);
            // }
            //! Old method


            $document = array(
                "project_id"                => $this->MongoDBObjectId($projectID),
                "traceability_data"         => $traceabilityData,
                "version"                   => "0.01",
                "is_edit"                   => null,
                "status"                    => null,
                "creator_id"                => $this->MongoDBObjectId($decoded->creater_by),
                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,
            );

            $result = $this->db->selectCollection("Traceability")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 400);

            return response()->json([
                "status" => "success",
                "message" => "ํYou add new traceability successfully !!",
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

    //* [PUT] /traceability/edit-delete
    public function editDeleteTraceability(Request $request)
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
                'traceability_id'        => 'required | string | min:1 | max:50',
                'traceability_data'      => 'required | array',
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
            $traceabilityID   = $request->traceability_id;
            $traceabilityData   = $request->traceability_data;

            $filter = ["_id" => $this->MongoDBObjectId($request->traceability_id)];
            $options = [
                "projection" =>
                [
                    "_id" => 0, "traceability_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "traceability_data" => 1
                ]
            ];

            $chkTraceID = $this->db->selectCollection("Traceability")->find($filter, $options);

            $dataChk = array();
            foreach ($chkTraceID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Traceability ID  not found", "data" => []], 400);


            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($traceabilityID)]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "creator_id" => 1,
                    "traceability_data" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                ]]
            ];
            $checkEdit = $this->db->selectCollection("Traceability")->aggregate($pipline);
            $checkEditData = array();
            foreach ($checkEdit as $doc) \array_push($checkEditData, $doc);

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

            // if is_edit isnot false, can edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status === null) {
                $updateDocument = $this->db->selectCollection("Traceability")->updateOne(
                    ['_id' => $this->MongoDBObjectId($traceabilityID)],
                    ['$set' => [
                        "traceability_data"         => $traceabilityData,
                        "updated_at"                => $timestamp,
                    ]]
                );
            }

            //! Old method
            // $queryTestCaseCode = $this->db->selectCollection("Traceability")->findOne($filter,$options)->traceability_data[0]->test_case_code;
            // $queryRequirementID = $this->db->selectCollection("Traceability")->findOne($filter,$options)->traceability_data[0]->requirement_code;

            // $softwareDesign          = $request -> software_design;
            // $dataSoftwareDesign = [];
            // foreach ($softwareDesign as $doc) \array_push($dataSoftwareDesign,$doc);

            // $softwareComponent       = $request -> software_component;
            // $dataSoftwareComponent = [];
            // foreach ($softwareComponent as $doc) \array_push($dataSoftwareComponent,$doc);

            // $dataList = [];

            // for ( $i = 0; $i < count($dataSoftwareDesign); $i++ ){
            //     $list = [
            //         "requirement_code"      => $queryRequirementID,
            //         "software_design"       => $dataSoftwareDesign[$i] ,
            //         "software_component"    => $dataSoftwareComponent[$i],
            //         "test_case_code"        => $queryTestCaseCode,
            //     ];
            //     array_push($dataList,$list);
            // }
            //! Old method


            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {
                $option = [
                    "project_id"                => $checkEditData[0]->project_id,
                    "creator_id"                => $checkEditData[0]->creator_id,
                    "traceability_data"         => $traceabilityData,

                    "version"                   => $checkEditData[0]->version . "_edit",
                    "is_edit"                   => true,
                    "status"                    => null,
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("Traceability")->updateOne(
                    ['_id' => $this->MongoDBObjectId($traceabilityID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );
                $insertForEditApproved = $this->db->selectCollection("StatementOfWork")->insertOne($option);
            }

            return response()->json([
                "status" => "success",
                "message" => "ํYou edit or delete new traceability successfully !!",
                "data" => [],
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


    //* [GET] /traceability/list-trace
    public function listTraceability(Request $request)
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

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


            // $pipeline = [
            //     ['$project' => ['_id' => 0, 'traceability_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'traceability_data' => 1,'version'=>1, 'is_edit'=>1, 'status'=>1,]]
            // ];

            $pipeline = [
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'creator_id' => 1, 'project_id' => 1, 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topic' => ['$arrayElemAt' => ['$TestCases.topics', 0]], 'traceability_data' => 1]],
                ['$project' => ['project_id' => ['$toString' => '$project_id'], 'traceability_id' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'sap_code' => 1, 'creator_name' => 1, 'project_name' => 1, 'project_type' => 1, 'traceability_data' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => [
                    'req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details', 'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$topic', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]],
                    'traceability_data' => ['$arrayElemAt' => [['$filter' => ['input' => '$traceability_data', 'as' => 'traceability_data', 'cond' => ['$eq' => ['$$traceability_data.req_code', '$$requirement.req_code']]]], 0]]
                ]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'test_case_name' => '$$temp1.test_case.topic']]]]], ['$group' => ['_id' => '$project_id', 'version' => ['$last' => '$version'], 'is_edit' => ['$last' => '$is_edit'], 'status' => ['$last' => '$status'], 'traceability_id' => ['$last' => '$traceability_id'], 'traceability_data' => ['$last' => '$traceability_data']]], ['$project' => ['_id' => 0, 'traceability_id' => 1, 'project_id' => '$_id', 'traceability_data' => 1]]
            ];

            $result = $this->db->selectCollection("Traceability")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "ํYou get list of traceability successfully !!",
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

    //* [GET] /traceability/get-all-status
    public function getTraceabilityStatus(Request $request)
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



            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


            $pipeline = [
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'traceability_id' => '$_id', 'project_id' => 1, 'project_name' => 1, 'is_edit' => 1, 'project_type' => 1, 'customer_name' => 1, 'version' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Approved']], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'traceability_id' => ['$toString' => '$traceability_id'], 'project_name' => 1, 'project_type' => 1, 'is_edit' => 1, 'customer_name' => 1, 'version' => 1, 'status' => '$is_verified', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]
            ];

            $result = $this->db->selectCollection("Traceability")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "ํYou get list of traceability successfully !!",
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

    //* [GET] /traceability/get-details-by-id
    public function getListReq(Request $request)
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
                'traceability_id'      => 'required | string | min:1 | max:50',
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

            $decoded = $jwt->decoded;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);
            $traceabilityID = $request->traceability_id;

            //! check data traceabilityID
            $filter = ['_id' => $this->MongoDBObjectId($traceabilityID)];
            $options = ["projection" => ["_id" => 0, "traceability_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("Traceability")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Traceability id not found", "data" => []], 400);

            //! check data traceabilityID

            // $pipeline = [
            // ['$match' => ['_id' => $this->MongoDBObjectId($traceabilityID)]],
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'case' => ['$arrayElemAt' => ['$TestCases.cases', 0]]]],
            //     ['$project' => ['project_id' => 1, 'traceability_id' => 1, 'traceability_data' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => ['req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details', 'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$case', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]]]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'test_case_name' => '$$temp1.test_case.test_case_name']]]]]
            // ];

            $pipeline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($traceabilityID)]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'version' => 1, 'status' => 1, 'creator_id' => 1, 'project_id' => 1, 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topic' => ['$arrayElemAt' => ['$TestCases.topics', 0]], 'traceability_data' => 1]], ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$project' => ['_id' => 0, 'version' => 1, 'sap_code' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects', 'pipeline' => [['$project' => ['_id' => 0, 'project_name' => 1, 'project_type' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1, 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => ['traceability_id' => 1, 'version' => 1, 'status' => 1, 'sap_code' => 1, 'creator_name' => 1, 'project_name' => 1, 'project_type' => 1, 'traceability_data' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => [
                    'req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details', 'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$topic', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]],
                    'traceability_data' => ['$arrayElemAt' => [['$filter' => ['input' => '$traceability_data', 'as' => 'traceability_data', 'cond' => ['$eq' => ['$$traceability_data.req_code', '$$requirement.req_code']]]], 0]]
                ]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'title' => '$$temp1.test_case.topic', 'software_design' => '$$temp1.traceability_data.software_design', 'software_component' => '$$temp1.traceability_data.software_component']]]]]
            ];

            $result = $this->db->selectCollection("Traceability")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "ํYou get details of Tracebility record by id successfully !!",
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

    // //* [GET] /traceability/get-recored
    // public function getTraceabilityRecored(Request $request)
    // {
    //     try {
    //         //! JWT
    //         $header = $request->header('Authorization');
    //         $jwt = $this->jwtUtils->verifyToken($header);
    //         if (!$jwt->state) return response()->json([
    //             "status" => "error",
    //             "message" => "Unauthorized",
    //             "data" => [],
    //         ], 401);

    //         $decoded = $jwt->decoded;
    // \date_default_timezone_set('Asia/Bangkok');
    // $date = date('Y-m-d H:i:s');
    // $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp()+2.52e4)*1000);


    //     $pipeline = [
    //         ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
    //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
    //         ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
    //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
    //         ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topic' => ['$arrayElemAt' => ['$TestCases.topics', 0]]]],
    //         ['$project' => ['project_id' => 1, 'traceability_id' => 1, 'traceability_data' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => ['req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details', 'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$topic', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]]]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'topic' => '$$temp1.test_case.topic']]]]]];


    //     $result = $this->db->selectCollection("Traceability")->aggregate($pipeline);

    //     $data = array();
    //     foreach ($result as $doc) \array_push($data, $doc);

    //     return response() -> json([
    //         "status" => "success",
    //         "message" => "ํYou get list of traceability successfully !!",
    //         "data" => $data
    //     ],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             "data"  =>[],
    //         ], $statusCode);
    //     }
    // }

    //! [POST] /traceability/get-individual-doc
    public function GetIndividualDoc(Request $request)
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
                'traceability_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $TraceID = $request->traceability_id;
            // $pipline = [
            //     ['$match' => ['_id' => $this->MongoDBObjectId($TraceID)]],
            //     ['$lookup' => ['from' => 'Traceability', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Traceability']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Traceability', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],

            //     ['$project' => [
            //         "_id" => 0,
            //         "traceability_id" => ['$toString' => '$_id'],
            //         "project_id" => ['$toString' => '$project_id'],
            //         "creator_id" => ['$toString' => '$creator_id'],
            //         "creator_name" => '$name_en',
            //         "version" => 1,
            //         "traceability_data" => 1,
            //         "document_name" => 1,
            //         "documentation" => 1,
            //         "sap_code" => 1,
            //         "status" => 1,
            //         "is_edit" => 1,
            //         "project_type" => 1,
            //         "customer_name" => 1,
            //     ]]
            // ];

            // $pipline = [
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'creator_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topic' => ['$arrayElemAt' => ['$TestCases.topics', 0]], 'traceability_data' => 1]],
            //     ['$project' => ['project_id' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'traceability_id' => 1, 'creator_id' => 1, 'traceability_data' => 1, 'traceability_datas' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => [
            //         'req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details',
            //         'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$topic', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]]
            //     ]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'topic' => '$$temp1.test_case.topic']]]]],
            //     ['$project' => ['project_id' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'traceability_id' => 1, 'traceability_data' => ['$map' => ['input' => ['$range' => [0, ['$size' => '$traceability_datas']]], 'as' => 'this', 'in' => ['$mergeObjects' => [
            //         ['$arrayElemAt' => ['$traceability_datas', '$$this']],
            //         ['$arrayElemAt' => ['$traceability_data', '$$this']]
            //     ]]]]]]
            // ];

            $pipline = [
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]], ['$lookup' => ['from' => 'TestCases', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'TestCases']], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]], ['$project' => ['_id' => 0, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'creator_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'traceability_id' => ['$toString' => '$_id'], 'software_requirement' => 1, 'topic' => ['$arrayElemAt' => ['$TestCases.topics', 0]], 'traceability_data' => 1]], ['$project' => ['project_id' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'traceability_id' => 1, 'creator_id' => 1, 'traceability_data' => 1, 'traceability_datas' => ['$map' => ['input' => ['$map' => ['input' => '$software_requirement', 'as' => 'requirement', 'in' => ['req_code' => '$$requirement.req_code', 'req_details' => '$$requirement.req_details', 'test_case' => ['$arrayElemAt' => [['$filter' => ['input' => '$topic', 'as' => 'testcase', 'cond' => ['$eq' => ['$$testcase.req_code', '$$requirement.req_code']]]], 0]]]]], 'as' => 'temp1', 'in' => ['req_code' => '$$temp1.req_code', 'req_details' => '$$temp1.req_details', 'test_case_code' => '$$temp1.test_case.test_case_code', 'topic' => '$$temp1.test_case.topic']]]]], ['$project' => ['project_id' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1, 'traceability_id' => 1, 'traceability_data' => ['$map' => ['input' => ['$range' => [0, ['$size' => '$traceability_datas']]], 'as' => 'this', 'in' => ['$mergeObjects' => [['$arrayElemAt' => ['$traceability_datas', '$$this']], ['$arrayElemAt' => ['$traceability_data', '$$this']]]]]]]], ['$group' => ['_id' => '$project_id', 'is_edit' => ['$last' => '$is_edit'], 'version' => ['$last' => '$version'], 'status' => ['$last' => '$status'], 'traceability_id' => ['$last' => '$traceability_id'], 'traceability_data' => ['$last' => '$traceability_data']]], ['$project' => ['_id' => 0, 'project_id' => '$_id', 'version' => 1, 'status' => 1, 'is_edit' => 1, 'traceability_id' => 1, 'traceability_data' => 1]]
            ];

            $TraceDoc = $this->db->selectCollection("Traceability")->aggregate($pipline);
            $dataTraceDoc = array();
            foreach ($TraceDoc as $doc) \array_push($dataTraceDoc, $doc);

            // return response()->json($dataTraceDoc);

            // if there is no documentation in the project
            if (\count($dataTraceDoc) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 404);

            $projectID = $dataTraceDoc[0]->project_id;

            // return response()->json($projectID);

            //     $cover =[
            //         ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //         ['$match' => ['version' => ['$lte' =>  $dataTraceDoc[0]->version]]],
            //         ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en', 'user_id' => 1]]]]],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
            //         ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
            //         ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$project' => ['_id' => 1, 'sap_code' => 1, 'version' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'customer_contact' => 1]]]]],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
            //         ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve']],
            //         ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType']],
            //         ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'sap_code' => 1,'status' => 1, 'conductor' => '$creator_name', 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'reviewer' => ['$arrayElemAt' => ['$customer_contact.name', 0]], 'verified_by' => 1,
            //             'verified_type' => 1, 'verified_at' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'verification_type' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
            //         ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'result_verified_by', 'pipeline' => [['$project' => ['_id' => 1, 'user_id' => 1, 'name' => '$name_en']]]]],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_verified_by', 0]], '$$ROOT']]]],

            //         ['$project' => ['_id' => 0, 'project_id' => 1, 'version' => 1, 'sap_code' => 1, 'status' => 1, 'conductor' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'reviewer' => 1, 'approver' => ['$arrayElemAt' => ['$result_verified_by.name', 0]], 'verified_type' => 1, 'verification_type' => 1, 'verified_at' => 1, 'created_at' => 1]]
            // ];

            $cover = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' =>  $dataTraceDoc[0]->version]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en', 'user_id' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$project' => ['_id' => 0, 'sap_code' => 1, 'version' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'customer_contact' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationType', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 1, 'user_id' => 1, 'sap_code' => 1, 'version' => 1, 'status' => 1, 'conductor' => '$creator_name', 'project_type' => 1, 'project_name' => 1, 'project_id' => 1, 'customer_name' => 1, 'reviewer' => ['$arrayElemAt' => ['$customer_contact.name', 0]], 'verified_by' => 1, 'verification_type' => 1, 'verified_at' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'aproved_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d']]
                ]],
                ['$lookup' => ['from' => 'Software', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Software']],
                ['$unwind' => '$Software'],
                ['$group' => [
                    '_id' => '$project_id', 'software_version' => ['$last' => '$Software.version'], 'verification_type' => ['$last' => '$verification_type'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'version' => ['$last' => '$version'], 'sap_code' => ['$last' => '$sap_code'],
                    'version' => ['$last' => '$version'], 'verified_by' => ['$last' => '$verified_by'], 'status' => ['$last' => '$status'], 'conductor' => ['$last' => '$conductor'], 'reviewer' => ['$last' => '$reviewer'], 'verified_at' => ['$last' => '$verified_at'], 'created_at' => ['$last' => '$created_at'], 'aproved_date' => ['$last' => '$aproved_date'],
                    'user_id' => ['$last' => '$user_id'], 'customer_name' => ['$last' => '$customer_name']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => '$_id', 'software_version' => 1, 'project_type' => 1, 'verification_type' => 1, 'project_name' => 1, 'version' => 1, 'customer_name' => 1, 'sap_code' => 1, 'software_version' => 1, 'verified_by' => 1, 'user_id' => 1, 'status' => 1, 'conductor' => 1, 'reviewer' => 1, 'verified_at' => 1, 'created_at' => 1, 'aproved_date' => 1]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'result_verified_by']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_verified_by', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'software_version' => 1, 'verification_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1, 'sap_code' => 1, 'project_type' => 1, 'software_version' => 1, 'verified_by' => 1, 'user_id' => 1, 'status' => 1, 'conductor' => 1, 'reviewer' => 1, 'verified_at' => 1, 'created_at' => 1, 'approver' => ['$arrayElemAt' => ['$result_verified_by.name_en', 0]], 'aproved_date' => 1]]
            ];


            $userCov = $this->db->selectCollection("Traceability")->aggregate($cover);
            $dataCover = array();
            // foreach ($userCov as $cov) \array_push($dataCover, $cov);

            foreach ($userCov as $cov) {
                if (str_ends_with((string)$cov->version, '.00')) {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "customer_name" => $cov->customer_name,
                        "project_name" => $cov->project_name,
                        "sap_code" => $cov->sap_code,
                        "project_type" => $cov->project_type,
                        "conductor" => ["conductor" => null, "created_at" => null],
                        "approver" => ["approver" => $cov->approver, "approve_at" => $cov->verified_at,],
                        "description" => "Approved",
                        "software_version" => $cov->software_version,
                    ];
                } else if ((string)$cov->version == '0.01') {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "customer_name" => $cov->customer_name,
                        "project_name" => $cov->project_name,
                        "sap_code" => $cov->sap_code,
                        "project_type" => $cov->project_type,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "approve_at" => null,],
                        "description" => "Created",
                        "software_version" => $cov->software_version,

                    ];
                } else {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "customer_name" => $cov->customer_name,
                        "project_name" => $cov->project_name,
                        "sap_code" => $cov->sap_code,
                        "project_type" => $cov->project_type,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "approve_at" => null,],
                        "description" => "Edited",
                        "software_version" => $cov->software_version,
                    ];
                }
                array_push($dataCover, $coverData);
            };
            // return response()->json($dataCover);

            // foreach ($userCov as $cov){
            //     if(str_ends_with((string)$cov->version, '.00')){
            //         $description = "Approved";
            //     }else if((string)$cov->version == '0.01'){
            //         $description = "Create";
            //     }else{
            //         $description = "Edited";
            //     }

            //     $checkApprover = $this->db->selectCollection("Traceability")->findOne(['_id'=>$this->MongoDBObjectId($TraceID)]);
            //     if($checkApprover->is_edit === null){
            //         $approver = null;
            //     }else{
            //         $approver = $cov->approver;
            //     }
            //     $coverData = [
            //         "project_id" => $cov->project_id,
            //         "version" => $cov->version,
            //         "conductor" => $cov->conductor,
            //         "reviewer" => $cov->reviewer,
            //         "approver" => $approver,
            //         "description" => $description,
            //     ];
            //     array_push($dataCover, $coverData);
            // };
            return response()->json([
                'status' => 'success',
                'message' => 'Get Traceability Documentation details successfully !!',
                "data" => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $dataTraceDoc,
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

    //! [GET] /traceability/get-doc // Get ducuments for each project_id
    public function GetTraceabilityDoc(Request $request)
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

            // $pipline = [
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
            //     ['$project' => [
            //         "_id" => ['$toString' => '$_id'],
            //         "project_id" => ['$toString' => '$project_id'],
            //         "project_name" => 1,
            //         "version" => 1,
            //         "is_edit" => 1,
            //         "status" => 1,
            //         "creator_id" => ['$toString' => '$creator_id'],
            //         "name_en" => 1,
            //         "customer_name" => 1,
            //         "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         "updated_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //     ]],
            //     [
            //         '$group' => [
            //             '_id' => [
            //                 'project_id' => '$project_id', 'project_name' => '$project_name', 'name_en' => '$name_en',
            //                 'customer_name' => '$customer_name',  'creator_id' => '$creator_id'
            //             ],
            //             'version' => ['$last' => '$version'], 'is_edit' => ['$last' => '$is_edit'],
            //             'status' => ['$last' => '$status'],
            //             'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id'],
            //             'updated_at' => ['$last' => '$updated_at']
            //         ]
            //     ],
            //     ['$project' => [
            //         "_id" => 0,
            //         "project_id" => '$_id.project_id',
            //         "project_name" => '$_id.project_name',
            //         "traceability_id" => '$document_id',
            //         "status" => 1,
            //         "customer_name" => '$_id.customer_name',
            //         "version" => 1,
            //         "created_at" => 1,
            //         "approved_at" => '$updated_at',
            //         "is_edit" => 1,
            //         "creator_id" => '$_id.creator_id',
            //         "creator_name" => '$_id.name_en'
            //     ]]
            // ];

            $pipline = [
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],

                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],

                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [
                    ['$sort' => ['created_at' => -1]],
                    ['$limit' => 1]
                ]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "name_en" => 1,
                    "customer_name" => 1,
                    "job_order" => 1,
                    "version" => 1,
                    "status" => 1,
                    "is_edit" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                [
                    '$group' => [
                        '_id' => ['project_id' => '$project_id', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'name_en' => '$name_en', 'job_order' => '$job_order'],
                        'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id'], 'updated_at' => ['$last' => '$updated_at'], 'version' => ['$last' => '$version'],
                        'status' => ['$last' => '$status'], 'is_edit' => ['$last' => '$is_edit'],
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => '$_id.project_id',
                    "job_order" => '$_id.job_order',
                    "version" => 1,
                    "status" => 1,
                    "is_edit" => 1,
                    "customer_name" => '$_id.customer_name',
                    "created_at" => 1,
                    "creator_name" => '$_id.name_en',
                    "approved_at" => '$_id.updated_at',

                ]]
            ];

            $userDoc = $this->db->selectCollection("Traceability")->aggregate($pipline);
            $dataUserDoc = array();

            foreach ($userDoc as $doc) {
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($doc->project_id)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            'version' => 1, 'traceability_id' => ['$toString' => '$_id'],
                            'project_name' => 1, 'status' => 1, 'is_edit' => 1, 'project_type' => 1,
                            'start_date' => 1,
                            'end_date' => 1
                        ]
                    ]
                ];

                $allVersion = $this->db->selectCollection("Traceability")->aggregate($pipline);
                $versionsAll = array();
                foreach ($allVersion as $ver) {
                    $version = $ver->version;
                    $docID = $ver->traceability_id;
                    $projectName = $ver->project_name;
                    $status = $ver->status;
                    $isEdit = $ver->is_edit;
                    $projectType = $ver->project_type;
                    $start = $ver->start_date;
                    $end = $ver->end_date;
                    array_push($versionsAll, [
                        "version" => $version, "traceability_id" => $docID,
                        "project_name" => $projectName, "status" => $status,
                        "is_edit" => $isEdit, "project_type" => $projectType,
                        'start_date' => $start, 'end_date' => $end

                    ]);
                }
                $versions = ["version_all" => $versionsAll];

                $data = array_merge((array)$doc, $versions);
                array_push($dataUserDoc, $data);
            }

            // return response()->json($dataUserDoc[0]->project_id);


            return response()->json([
                'status' => 'success',
                'message' => 'Get all Traceability Documentation successfully !!',
                "data" => $dataUserDoc,
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
