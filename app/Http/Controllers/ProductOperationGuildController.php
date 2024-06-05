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

class ProductOperationGuildController extends Controller
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

    // upload file (Create)
    public function UploadOperationGuild(Request $request)
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
                "project_id" => "required | string | min:1 | max:255",
                "document_name" => "required | string | min:1 | max:255",
                "documentation" => "required | string | min:1",
                "size_doc" => "required | numeric"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }


            $projectID      = $request->project_id;
            $decoded = $jwt->decoded;
            $creatorID = $decoded->creater_by;
            $documentName = $request->document_name;
            $documentation = $request->documentation;
            $sizeDoc = $request->size_doc;
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            // // Need to pass UAT
            // $pipline = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$sort'=>['created_at' => 1]],
            //     ['$group' => ['_id' => ['project_id'=>'$project_id'], 'status'=> ['$last' => '$status']]],
            //     ['$project' => [
            //         "_id" => 0,
            //         "status" => 1,
            //     ]]
            // ];
            // $checkStatus = $this->db->selectCollection("UAT")->aggregate($pipline);
            // $dataCheckStatus = array();
            // foreach ($checkStatus as $doc) \array_push($dataCheckStatus, $doc);

            // if($dataCheckStatus[0]->status !== true){
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'UAT is not verified',
            //         "data" => [],
            //     ], 400);
            // }

            // if documrnt has been created, cannot create again
            $checkDoc = $this->db->selectCollection("ProductOperationGuide")->findOne(['project_id' => $this->MongoDBObjectId($projectID)]);
            if ($checkDoc !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This document has been created',
                    "data" => [],
                ], 400);
            }

            // // prevent exceed size
            // if($sizeDoc > 3){
            //     return response() -> json([
            //         "status" => "error",
            //         "message" => "Document size cannot exceed 3MB",
            //         "data" => []
            //     ], 400);
            // }

            $path = getcwd() . "\\..\\images\\OperationGuild\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/images/OperationGuild/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/OperationGuild/"; //server
            $fileName = $documentName . "_0.01" . "_" . $timestamp . ".pdf";

            if (str_starts_with($documentation, 'data:application/pdf;base64,')) {
                //save file to server
                $folderPath = $path  . "\\";
                if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentation)));
                $softwareUserDoc = $pathUsed . $fileName;
            }

            $option = [
                "project_id" => $this->MongoDBObjectId($projectID),
                "creator_id" => $this->MongoDBObjectId($creatorID),
                "document_name" => $documentName,
                "documentation" => $softwareUserDoc,
                "size_doc" => $sizeDoc,
                "version" => "0.01",
                "is_edit" => null,
                "status" => null,
                "created_at" => $timestamp,
                "updated_at" => $timestamp,
            ];
            $insertnew = $this->db->selectCollection("ProductOperationGuide")->insertOne($option);

            return response()->json([
                "status" => "success",
                "message" => "Document uploaded successfully",
                "data" => [$insertnew->getInsertedCount()]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    // Get ducuments for each project_id
    public function GetOperationGuild(Request $request)
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
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    'job_order' => 1,
                    "project_id" => ['$toString' => '$project_id'],
                    "project_name" => 1,
                    "name_en" => 1,
                    "customer_name" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "version" => 1,
                    'is_edit' => 1,
                    'status' => 1,
                ]],
                [
                    '$group' => [
                        '_id' => ['project_id' => '$project_id', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'name_en' => '$name_en'],
                        'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id'], "is_edit" => ['$last' => '$is_edit'], "status" => ['$last' => '$status'],
                        "version" => ['$last' => '$version'], 'job_order' => ['$last' => '$job_order']
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    'job_order' => 1,
                    "project_id" => '$_id.project_id',
                    "customer_name" => '$_id.customer_name',
                    "created_at" => 1,
                    "creator_name" => '$_id.name_en',
                    "is_edit" => 1,
                    "status" => 1,
                    'version' => 1,
                    "project_name" => '$_id.project_name',
                ]]
            ];

            $userDoc = $this->db->selectCollection("ProductOperationGuide")->aggregate($pipline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) {
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($doc->project_id)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            'version' => 1, 'guild_id' => ['$toString' => '$_id'],
                            'project_name' => 1, 'status' => 1, 'is_edit' => 1, 'project_type' => 1,
                            'start_date' => 1,
                            'end_date' => 1
                        ]
                    ]
                ];
                $allVersion = $this->db->selectCollection("ProductOperationGuide")->aggregate($pipline);
                $versionsAll = array();
                foreach ($allVersion as $ver) {
                    if (!array_key_exists('project_name', (array)$ver)) {
                        return response()->json([
                            "status" => "error",
                            "message" => "This project dosen't exsit in the project",
                            "data" => []
                        ], 404);
                    }
                    $version = $ver->version;
                    $guildID = $ver->guild_id;
                    $projectName = $ver->project_name;
                    $status = $ver->status;
                    $isEdit = $ver->is_edit;
                    $projectType = $ver->project_type;
                    $start = $ver->start_date;
                    $end = $ver->end_date;
                    array_push($versionsAll, [
                        "version" => $version, "guild_id" => $guildID,
                        "project_name" => $projectName, "status" => $status, "is_edit" => $isEdit, "project_type" => $projectType,
                        'start_date' => $start, 'end_date' => $end
                    ]);
                }
                $versions = ["version_all" => $versionsAll];

                $data = array_merge((array)$doc, $versions);
                array_push($dataUserDoc, $data);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get all Software User Documentation successfully !!',
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

    // Get individual document
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
                'guild_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $guildID = $request->guild_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($guildID)]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "guild_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "project_name" => 1,
                    "creator_id" => ['$toString' => '$creator_id'],
                    "creator_name" => '$name_en',
                    "version" => 1,
                    "document_name" => 1,
                    "documentation" => 1,
                    "size_doc" => 1,
                    "sap_code" => 1,
                    "project_type" => 1,
                    "customer_name" => 1,
                ]]
            ];
            $userDoc = $this->db->selectCollection("ProductOperationGuide")->aggregate($pipline);
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
            $cover = [
                ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
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
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "verified_at" => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "validated_at" => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "version" => 1,
                    "conductor" => 1,
                    "reviewer" => 1,
                    "approver" => '$name_en',
                    "created_at" => 1,
                    "verified_at" => 1,
                    "validated_at" => 1,
                ]],
            ];
            $userCov = $this->db->selectCollection("ProductOperationGuide")->aggregate($cover);
            $dataCover = array();
            // foreach ($userCov as $cov) \array_push($dataCover, $cov);

            foreach ($userCov as $cov) {
                if (str_ends_with((string)$cov->version, '.00')) {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => null, "created_at" => null],
                        "approver" => ["approver" => $cov->approver, "verified_at" => $cov->verified_at],
                        "description" => "Approved",
                    ];
                } else if ((string)$cov->version == '0.01') {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "description" => "Created",
                    ];
                } else {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "description" => "Edited",
                    ];
                }
                array_push($dataCover, $coverData);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get Software User Documentation details successfully !!',
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

    // Edit document
    public function EditOperationGuild(Request $request)
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
                "guild_id" => "required | string | min:1 | max:255",
                "documentation" => "required | string | min:1",
                "size_doc" => "required | numeric"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $guildID = $request->guild_id;
            $documentation = $request->documentation;
            $sizeDoc = $request->size_doc;
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            // // prevent exceed size
            // if($sizeDoc > 3){
            //     return response() -> json([
            //         "status" => "error",
            //         "message" => "Document size cannot exceed 3MB",
            //         "data" => []
            //     ], 400);
            // }

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($guildID)]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "document_name" => 1,
                    "size_doc" => 1,
                    "creator_id" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                ]]
            ];
            $checkEdit = $this->db->selectCollection("ProductOperationGuide")->aggregate($pipline);
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

            $documentName = $checkEditData[0]->document_name;
            $version = $checkEditData[0]->version;

            // If is_edit is fasle, cannot edit
            if ($checkEditData[0]->is_edit === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot edit this document',
                    "data" => [],
                ], 400);
            }

            $path = getcwd() . "\\..\\images\\OperationGuild\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/images/OperationGuild/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/OperationGuild/"; //server
            $fileName = $documentName . "_" . $version . "_" . $timestamp . ".pdf";


            // if is_edit isnot false, can edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status === null) {
                if (str_starts_with($documentation, 'data:application/pdf;base64,')) {
                    // delete existing file
                    if (is_dir($path)) {
                        $files = scandir($path);
                        $files = array_diff($files, array('.', '..'));
                        foreach ($files as $file) {
                            // Check if the file name contains the word "result"
                            if (strpos($file, $documentName . "_" . $version) !== false) {
                                // Delete the file
                                unlink($path . '/' . $file);
                            }
                        }
                    }

                    //save file to server
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentation)));
                    $softwareUserDoc = $pathUsed . $fileName;
                }

                $updateDocument = $this->db->selectCollection("ProductOperationGuide")->updateOne(
                    ['_id' => $this->MongoDBObjectId($guildID)],
                    ['$set' => [
                        "documentation" => $softwareUserDoc,
                        "size_doc" => $sizeDoc,
                        "updated_at" => $timestamp,
                    ]]
                );
            }

            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {

                if (str_starts_with($documentation, 'data:application/pdf;base64,')) {
                    // copy existing file
                    if (is_dir($path)) {
                        $files = scandir($path);
                        $files = array_diff($files, array('.', '..'));
                        foreach ($files as $file) {
                            // Check if the file name contains the word "result"
                            if (strpos($file, $documentName . "_" . $version) !== false) {
                                $filePath = $path . DIRECTORY_SEPARATOR . $file;
                                $copyToFilePath = $path . DIRECTORY_SEPARATOR . $file . "_edit.pdf";
                                copy($filePath, $copyToFilePath);
                                $editingFile = $pathUsed . $file . "_edit.pdf";
                            }
                        }
                    }
                }

                $option = [
                    "project_id" => $checkEditData[0]->project_id,
                    "creator_id" => $checkEditData[0]->creator_id,
                    "document_name" => $checkEditData[0]->document_name,
                    "documentation" => $editingFile,
                    "size_doc" => $checkEditData[0]->size_doc,
                    "version" => $checkEditData[0]->version . "_edit",
                    "is_edit" => true,
                    "status" => null,
                    "created_at" => $timestamp,
                    "updated_at" => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("ProductOperationGuide")->updateOne(
                    ['_id' => $this->MongoDBObjectId($guildID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );
                $insertForEditApproved = $this->db->selectCollection("ProductOperationGuide")->insertOne($option);
            }

            return response()->json([
                "status" => "success",
                "message" => "updated successfully",
                "data" => []
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }
}
