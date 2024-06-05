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

class SoftwareReqController extends Controller
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

    // create new SRS
    public function NewReqSpecification(Request $request)
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
                'project_id'            => 'required | string | min:1 | max:255',
                'software_requirement'  => 'required | array',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);
            $projectID      = $request->project_id;
            $softwareReq    = $request->software_requirement;
            $decoded = $jwt->decoded;
            $creatorID = $decoded->creater_by;

            // // Need to pass project planing
            // $pipline = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$sort'=>['created_at' => 1]],
            //     ['$group' => ['_id' => ['project_id'=>'$project_id'], 'status'=> ['$last' => '$status']]],
            //     ['$project' => [
            //         "_id" => 0,
            //         "status" => 1,
            //     ]]
            // ];
            // $checkStatus = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipline);
            // $dataCheckStatus = array();
            // foreach ($checkStatus as $doc) \array_push($dataCheckStatus, $doc);

            // if($dataCheckStatus[0]->status !== true){
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Project planing is not verified',
            //         "data" => [],
            //     ], 400);
            // }

            // if documrnt has been created, cannot create again
            $checkDoc = $this->db->selectCollection("SoftwareReqSpecification")->findOne(['project_id' => $this->MongoDBObjectId($projectID)]);
            if ($checkDoc !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This document has been created',
                    "data" => [],
                ], 400);
            }

            $option = [
                "project_id" => $this->MongoDBObjectId($projectID),
                "creator_id" => $this->MongoDBObjectId($creatorID),
                "software_requirement" => $softwareReq,
                "version" => "0.01",
                "is_edit" => null,
                "status" => null,
                "created_at" => $timestamp,
                "updated_at" => $timestamp,
            ];

            $insertnew = $this->db->selectCollection("SoftwareReqSpecification")->insertOne($option);
            return response()->json([
                'status' => 'success',
                'message' => 'New Software Requirement Specification has been created successfully',
                "data" => [$insertnew->getInsertedCount()],
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


    // get all SRS
    public function GetReqSpecification(Request $request)
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

            $pipline = [
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    'job_order' => 1,
                    "project_id" => ['$toString' => '$project_id'],
                    "project_name" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "creator_id" => ['$toString' => '$creator_id'],
                    "name_en" => 1,
                    "customer_name" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                [
                    '$group' => [
                        '_id' => [
                            'project_id' => '$project_id', 'project_name' => '$project_name', 'name_en' => '$name_en',
                            'customer_name' => '$customer_name',  'creator_id' => '$creator_id'
                        ],
                        'version' => ['$last' => '$version'], 'is_edit' => ['$last' => '$is_edit'],
                        'status' => ['$last' => '$status'],
                        'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id'], "job_order" => ['$last' => '$job_order']
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    "job_order" => 1,
                    "project_id" => '$_id.project_id',
                    "project_name" => '$_id.project_name',
                    "software_req_id" => '$document_id',
                    "status" => 1,
                    "customer_name" => '$_id.customer_name',
                    "version" => 1,
                    "created_at" => 1,
                    "is_edit" => 1,
                    "creator_id" => '$_id.creator_id',
                    "creator_name" => '$_id.name_en'
                ]]
            ];

            $userDoc = $this->db->selectCollection("SoftwareReqSpecification")->aggregate($pipline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) {
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($doc->project_id)]],
                    ['$project' => ['version' => 1, 'software_req_id' => ['$toString' => '$_id']]]
                ];
                $allVersion = $this->db->selectCollection("SoftwareReqSpecification")->aggregate($pipline);
                $versionsAll = array();
                foreach ($allVersion as $ver) {
                    $version = $ver->version;
                    $docID = $ver->software_req_id;
                    array_push($versionsAll, ["version" => $version, "software_req_id" => $docID]);
                }
                $versions = ["version_all" => $versionsAll];

                $data = array_merge((array)$doc, $versions);
                array_push($dataUserDoc, $data);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get all Software Requirement Specification successfully !!',
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

    // get SRS details
    public function GetDetails(Request $request)
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
                'software_req_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $softwareReqID = $request->software_req_id;
            $pipelineUserDoc = [
                ['$match' => ['_id' => $this->MongoDBObjectId($softwareReqID)]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                [
                    '$project' => [
                        '_id' => 0, 'software_req_id' => ['$toString' => '$_id'],
                        'project_id' => ['$toString' => '$project_id'],
                        'sap_code' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                        'approved_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        'creator_name' => '$name_en',
                        'introduction_of_project' => 1, 'list_of_introduction' => 1,
                        'system_requirement' => '$objective_of_project', 'software_requirement' => 1, 'functionality' => ['$arrayElemAt' => ['$ProjectsPlaning.software_requirement', 0]],
                        'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    ]
                ],
                [
                    '$project' => [
                        '_id' => 0, 'software_req_id' => 1,
                        'project_id' => 1, 'sap_code' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                        'approved_date' => 1, 'creator_name' => 1, 'introduction_of_project' => 1, 'list_of_introduction' => 1,
                        'software_requirement' => ['$map' => ['input' => ['$range' => [0, ['$min' => [['$size' => '$software_requirement'], ['$size' => '$functionality']]]]], 'as' => 'index', 'in' => ['$mergeObjects' => [['$arrayElemAt' => ['$software_requirement', '$$index']], ['$arrayElemAt' => ['$functionality', '$$index']]]]]],
                        'system_requirement' => 1, 'created_at' => 1, 'updated_at' => 1
                    ]
                ]
            ];
            $userDoc = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($pipelineUserDoc);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);

            // if there is no documentation in the project
            if (\count($dataUserDoc) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 404);

            $projectID = $dataUserDoc[0]->project_id;

            $reviseHistory = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$project' => [
                    "project_id" => ['$toString' => '$project_id'],
                    "version" => 1,
                    "conductor" => '$name_en',
                    "reviewer" => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                    "verified_by" => 1,
                    "verification_type" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "verified_at" => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "validated_at" => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationType', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "conductor" => 1,
                    "version" => 1,
                    "reviewer" => 1,
                    "approver" => '$name_en',
                    "created_at" => 1,
                    "verified_at" => 1,
                    "validated_at" => 1,
                    "approver_number" => 1,
                    "verification_type" => 1,
                ]],
            ];
            $userCov = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($reviseHistory);
            $dataCover = array();
            // foreach ($userCov as $cov) \array_push($dataCover, $cov);
            // return response()->json($dataCover);

            foreach ($userCov as $cov) {
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
                            "approver" => $approver,
                            "reviewer" => $reviewer,
                            "description" => "Approved",
                            "created_at" => null,
                            "verified_at" =>  $verified_at,
                            "validated_at" => $validated_at,
                        ];
                        array_push($dataCover, $coverData);
                    }
                } else if ((string)$cov->version == '0.01') {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => $cov->conductor,
                        "approver" => null,
                        "reviewer" => null,
                        "description" => "Created",
                        "created_at" => $cov->created_at,
                        "verified_at" => null,
                        "validated_at" => null,
                    ];
                    array_push($dataCover, $coverData);
                } else {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => $cov->conductor,
                        "approver" => null,
                        "reviewer" => null,
                        "description" => "Edited",
                        "created_at" => $cov->created_at,
                        "verified_at" => null,
                        "validated_at" => null,
                    ];
                    array_push($dataCover, $coverData);
                }
            };
            return response()->json([
                'status' => 'success',
                'message' => 'Get Software Requirement Specification details successfully !!',
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

    // edit SRS
    public function EditReqSpecification(Request $request)
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
                'software_req_id'       => 'required | string | min:1 | max:255',
                'software_requirement'  => 'required | array',
            ]);

            $softwareReqID = $request->software_req_id;
            $softwareReq = $request->software_requirement;
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);
            $decoded = $jwt->decoded;
            $editerID = $decoded->creater_by;

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($softwareReqID)]],
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
            $checkEdit = $this->db->selectCollection("SoftwareReqSpecification")->aggregate($pipline);
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

            // return response()->json($checkEditData);

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
                $updateDocument = $this->db->selectCollection("SoftwareReqSpecification")->updateOne(
                    ['_id' => $this->MongoDBObjectId($softwareReqID)],
                    ['$set' => [
                        "software_requirement" => $softwareReq,
                        "updated_at" => $timestamp,
                    ]]
                );
            }

            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {
                $option = [
                    "project_id" => $checkEditData[0]->project_id,
                    "creator_id" => $checkEditData[0]->creator_id,
                    "software_requirement" => $softwareReq,
                    "version" => $checkEditData[0]->version . "_edit",
                    "is_edit" => true,
                    "status" => null,
                    "created_at" => $timestamp,
                    "updated_at" => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("SoftwareReqSpecification")->updateOne(
                    ['_id' => $this->MongoDBObjectId($softwareReqID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );
                $insertForEditApproved = $this->db->selectCollection("SoftwareReqSpecification")->insertOne($option);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Software Requirement Specification has been updated successfully',
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
}
