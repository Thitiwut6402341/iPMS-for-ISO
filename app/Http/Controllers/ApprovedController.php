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
use App\Mail\ValidationResponse;

class ApprovedController extends Controller
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


    //* [POST] /approved/send-verified
    public function sendVerifiedStatement(Request $request)
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
            $createrID = $decoded->creater_by;

            $rules = [
                'document_id'               => 'required | string | min:1 | max:255',
                'verification_type'         => ['required', 'string', Rule::in(["SOW", "PROJECT_PLAN", "PROJECT_REPO", "PROJECT_BACKUP", "SRS", "DESIGN", "TEST_CASES", "TEST_REPORT", "USER_DOC", "PRODUCT_OPERATION_GUIDE", "MAINTENANCE_DOC", "TRACEABILITY_RECORD", "CHANGE_REQUEST", "ACCEPT_RECORD", "SOFTWARE_CONFIG", "VERIFICATION_RESULTS", "VALIDATION_RESULTS", "UAT"])],
                // 'title'                     => 'required | string | min:1 | max:255',
                // 'description'               => 'required | string | min:1 | max:500',
                // "approver_id"               => 'required | string | min:1 | max:255',
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

            $documentID     = $request->document_id;

            // cannot send to verify if sent before
            $sentApproved = $this->db->selectCollection('Approved')->findOne(["document_id" => $this->MongoDBObjectId($documentID)]);
            if ($sentApproved) {
                return response()->json([
                    "status" => "error",
                    "message" => "This document has been sent to verify already, please send another document",
                    "data" => []
                ], 400);
            }

            $approverID           = "65f8ff160ef792b118003f87";
            $verificationType    = $request->verification_type;
            // $title              = $request -> title;
            // $description        = $request -> description;
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $data = $this->db->selectCollection('VerificationType')->find(["verification_type" => $verificationType]);
            $verType = array();
            foreach ($data as $doc) \array_push($verType, $doc);
            $collectionName = $verType[0]->collection_name;
            $approverNo = $verType[0]->approver_number;

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                ['$project' => ["project_id" => ['$toString' => '$project_id']]],
            ];

            $project      = $this->db->selectCollection($collectionName)->aggregate($pipline);
            $dataProject = array();
            foreach ($project as $doc) \array_push($dataProject, $doc);

            // if there is no documentation in the project
            if (\count($dataProject) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 404);

            $projectID = $dataProject[0]->project_id;
            // return response()->json($projectID);

            $filter2 = ["_id" => $this->MongoDBObjectId($documentID)];
            $options2 = ["limit" => 1, "projection" => ["_id" => 0, "document_id" => ['$toString' => '$_id'], "project_id" => ['$toString' => '$project_id'], "teamspace_id" => ['$toString' => '$teamspace_id'], "is_approved" => 1,]];
            $chkStatement = $this->db->selectCollection($collectionName)->find($filter2, $options2);

            $dataChk2 = array();
            foreach ($chkStatement as $doc) \array_push($dataChk2, $doc);

            // return response()->json($dataChk2);

            if (\count($dataChk2) == 0)
                return response()->json(["status" => "error", "message" => "Document statement of work dosen't exsit", "data" => []], 500);
            //! check data document and project id

            // Need verify and validate
            if ($approverNo == 2) {
                $document = [
                    "project_id"                => $this->MongoDBObjectId($projectID),
                    "creator_id"                => $this->MongoDBObjectId($createrID),
                    "document_id"               => $this->MongoDBObjectId($documentID),
                    "verification_type"         => $verificationType,
                    // "title"                     => $title,
                    // "description"               => $description,
                    "is_verified"               => null,
                    "verified_at"               => null,
                    "verified_by"               => $this->MongoDBObjectId($approverID),
                    "is_validated"               => null,
                    "validated_at"               => null,
                    "validated_by"               => null,
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];

                // Need verify only
            } else if ($approverNo == 1) {
                $document = [
                    "project_id"                => $this->MongoDBObjectId($projectID),
                    "creator_id"                => $this->MongoDBObjectId($createrID),
                    "document_id"               => $this->MongoDBObjectId($documentID),
                    "verification_type"         => $verificationType,
                    // "title"                     => $title,
                    // "description"               => $description,
                    "is_verified"               => null,
                    "verified_at"               => null,
                    "verified_by"               => $this->MongoDBObjectId($approverID),
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];
            }

            // save as in first times
            $fisrt = $this->db->selectCollection($collectionName)->find(["project_id" => $this->MongoDBObjectId($projectID)]);
            $dataFisrt = array();
            foreach ($fisrt as $doc) \array_push($dataFisrt, $doc);

            // save as, version must be changed.
            $checkVersion = $this->db->selectCollection($collectionName)->find(["_id" => $this->MongoDBObjectId($documentID)]);
            $dataVersion = array();
            foreach ($checkVersion as $doc) \array_push($dataVersion, $doc);
            $version = $dataVersion[0]->version;

            $newVersion = substr($version, 0, 4);
            $newVersion = $newVersion + 0.01;
            $newVersion = (string)$newVersion;
            $newDocumentation = null;

            // do this only uploaded documentation
            if (in_array($verificationType, ["USER_DOC", "PRODUCT_OPERATION_GUIDE", "MAINTENANCE_DOC"])) {
                $documentName = $dataVersion[0]->document_name;
                // rename file if any
                $path = getcwd() . "\\..\\images\\SoftwareUserDocumentation\\";
                // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/images/SoftwareUserDocumentation/'; // local
                $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/SoftwareUserDocumentation/"; //server
                // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
                \date_default_timezone_set('Asia/Bangkok');
                $date = date('Y-m-d H:i:s');
                $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

                if (is_dir($path)) {
                    $files = scandir($path);
                    $files = array_diff($files, array('.', '..'));
                    foreach ($files as $file) {
                        // Check if the file name contains the word "result"
                        if (strpos($file, 'edit') !== false) {
                            $filePath = $path . DIRECTORY_SEPARATOR . $file;
                            $newFileName = $documentName . "_" . $newVersion . "_" . $timestamp . ".pdf";
                            rename($filePath, $path . DIRECTORY_SEPARATOR . $newFileName);
                            $newDocumentation = $pathUsed . $newFileName;
                        }
                    }
                }
            }

            if (count($dataFisrt) == 1) {
                $saveAs = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_edit" => false, 'is_sent_verified' => true, 'quotation' => null, "verified_by" => $this->MongoDBObjectId($approverID), "updated_at" => $timestamp]]);
            } else if ((in_array($verificationType, ["USER_DOC", "PRODUCT_OPERATION_GUIDE", "MAINTENANCE_DOC"]))) {
                $saveAs = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_edit" => false, 'is_sent_verified' => true, 'quotation' => null, "verified_by" => $this->MongoDBObjectId($approverID), "version" => $newVersion, "documentation" => $newDocumentation, "updated_at" => $timestamp]]);
            } else {
                $saveAs = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_edit" => false, 'is_sent_verified' => true, 'quotation' => null, "verified_by" => $this->MongoDBObjectId($approverID), "version" => $newVersion, "updated_at" => $timestamp]]);
            }

            $result = $this->db->selectCollection('Approved')->insertOne($document);

            return response()->json([
                "status" => "success",
                "message" => "Sent verify successfully !!",
                "data" => [$result->getInsertedCount()]
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

    // get verification list (wait, approve, reject)
    public function VerificationList(Request $request)
    {
        try {
            // wait to verify
            $pipeline = [
                ['$group' => [
                    '_id' => ['project_id' => '$project_id', 'verification_type' => '$verification_type'],
                    'document_id' => ['$last' => '$document_id'], 'status' => ['$last' => '$is_verified']
                ]],
                ['$project' => [
                    '_id' => 0, 'project_id' => '$_id.project_id', 'verification_type' => '$_id.verification_type',
                    'document_id' => 1, 'status' => 1
                ]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationType', 0]], '$$ROOT']]]],

                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'StatementOfWork']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'ProjectsPlaning']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'SoftwareReqSpecification', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'SoftwareReqSpecification']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$SoftwareReqSpecification', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'SoftwareDesign', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'SoftwareDesign']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$SoftwareDesign', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'TestCases', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'TestCases']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestCases', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'TestReport', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'TestReport']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$TestReport', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'SoftwareUserDocs', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'SoftwareUserDocs']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$SoftwareUserDocs', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProductOperationGuide', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'ProductOperationGuide']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProductOperationGuide', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'MaintenanceDocs', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'MaintenanceDocs']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$MaintenanceDocs', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Traceability', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'Traceability']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Traceability', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'iCSD_Correctionregister', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'iCSD_Correctionregister']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$iCSD_Correctionregister', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'AcceptRecord', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'AcceptRecord']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$AcceptRecord', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'UAT', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'UAT']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$UAT', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationValidation', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'VerificationValidation']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationValidation', 0]], '$$ROOT']]]],

                ['$project' => [
                    "_id" => 0, 'project_id' => ['$toString' => '$project_id'], 'project_name' => 1, 'work_product' => 1, 'is_edit' => 1,
                    "status" => 1, 'document_id' => ['$toString' => '$document_id'], 'baseline' => '$version', 'verification_type' => 1,
                    'customer_name' => 1, 'sent_date' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]]

            ];
            $data = $this->db->selectCollection('Approved')->aggregate($pipeline);
            $dataAll = array();
            foreach ($data as $doc) \array_push($dataAll, $doc);

            return response()->json([
                'status' => 'success',
                'message' => 'Get verification list successfully',
                "data" => $dataAll,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    // get project detailq from wait list
    public function ProjectDocumentation(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $validator = Validator::make($request->all(), [
                "project_id" => "required | string | min:1 | max:255",
            ]);
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

            $decoded = $jwt->decoded;
            $teamspaceName = $decoded->teamspace_name;
            $projectID = $request->project_id;
            $testCaseId = null;

            $coverPipeline = [
                ['$lookup' => ['from' => 'Software', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Software', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Software', 0]], '$$ROOT']]]],



                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => [
                    '_id' => 0, 'sap_code' => 1, 'project_type' => 1,  'project_name' => 1, 'quotation' => 1, 'customer_name' => 1, 'check_date' => null,
                    'qa_name' => 'Nathaphart Bangkerd', 'version' => '1.00', 'software_version' => '$version', 'approved_date' => null,
                    'revision_history' => [
                        ['version' => 0.01, 'description' => 'Generate Verification & Validation Result & Software Configuration', 'conductor' => 'Sataporn Chaijaroen', 'approver' => null],
                        ['version' => 1.00, 'description' => 'Approved', 'conductor' => null, 'approver' => 'Nathaphart Bangkerd']
                    ]
                ]]
            ];
            $cover = $this->db->selectCollection('StatementOfWork')->aggregate($coverPipeline);
            $dataCover = array();
            foreach ($cover as $doc) \array_push($dataCover, $doc);

            $pipeline = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['is_sent_verified' => ['$eq' => true]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork2', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork2', 0]], '$$ROOT']]]],
                ['$sort' => ['created_at' => -1]],
                ['$sort' => ['version' => -1]],
                ['$limit' => 1],
                ['$project' => [
                    '_id' => 1, 'baseline' => '$version', 'status' => 1, 'qa_name' => '$name_en', 'validation_person' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                    'position_id' => 1, "quotation" => 1,
                ]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0, 'Position' => 1, 'document_id' => ['$toString' => '$_id'], 'baseline' => 1, 'status' => 1, 'qa_name' => 1, "quotation" => 1, 'verified_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'validation_person' => 1, 'validated_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']]
                ]],
            ];

            $pipelineNoVerify = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork2', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork2', 0]], '$$ROOT']]]],
                ['$sort' => ['created_at' => -1]],
                ['$sort' => ['version' => -1]],
                ['$limit' => 1],
                ['$project' => [
                    '_id' => 1, 'baseline' => '$version', 'status' => 1, 'qa_name' => '$name_en', 'validation_person' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                    'position_id' => 1, "quotation" => 1, "software_component" => 1, "software" => 1
                ]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0, 'Position' => 1, 'document_id' => ['$toString' => '$_id'], 'baseline' => 1, 'status' => 1, 'qa_name' => 1, "quotation" => 1, 'verified_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'validation_person' => 1, 'validated_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']], "software_component" => 1, "software" => 1
                ]],
            ];

            $pipelineSOW = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['is_sent_verified' => ['$eq' => true]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$sort' => ['created_at' => -1]],
                ['$sort' => ['version' => -1]],
                ['$limit' => 1],
                ['$project' => [
                    '_id' => 0, 'document_id' => ['$toString' => '$_id'], 'work_product' => "STATEMENT OF WORK: SOW", 'baseline' => '$version', 'status' => 1,
                    'doc_link' => "Statement of Work",
                    'qa_name' => '$name_en', 'verified_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'validation_person' => ['$arrayElemAt' => ['$costomer_contact.name', 0]],
                    'validated_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'position_id' => 1, "quotation" => 1,
                ]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0, 'document_id' => 1, 'work_product' => 1, 'baseline' => 1, 'status' => 1,
                    'doc_link' => 1, 'link' => ['$concat' => ['/sow/view/', '$document_id']],
                    'qa_name' => 1, 'position' => '$Position', 'verified_date' => 1,
                    'validation_person' => 1, 'validated_date' => 1, 'verification_type' => "SOW", "quotation" => 1,
                ]],
            ];

            // Statement of Work
            $SOW = $this->db->selectCollection('StatementOfWork')->aggregate($pipelineSOW);
            $projectDoc = array();
            foreach ($SOW as $doc) \array_push($projectDoc, $doc);

            // Project planing
            $PP = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipeline);
            foreach ($PP as $doc) {
                if ($doc->validated_date === null) {
                    $doc->validation_person = null;
                }
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'PROJECT PLANING: PP', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Project Planing', 'link' => '/projects-planning/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "PROJECT_PLAN",
                    "quotation" => $doc->quotation
                ];
                array_push($projectDoc, $data);
            }

            // Software requirement specification
            $SRS = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($pipeline);
            foreach ($SRS as $doc) {
                if ($doc->validated_date === null) {
                    $doc->validation_person = null;
                }
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE REQUIREMENT SPECIFICATION: SRS', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software Requirement Specification', 'link' => '/srs/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "SRS",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // Software design
            $SD = $this->db->selectCollection('SoftwareDesign')->aggregate($pipeline);
            foreach ($SD as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE DESIGN: SD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software Design', 'link' => '/software-design/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "DESIGN",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // Test Cases
            $TC = $this->db->selectCollection('TestCases')->aggregate($pipeline);
            foreach ($TC as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'TEST CASES: TC', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Test Cases', 'link' => '/tms/repositories/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "TEST_CASES",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
                $testCaseId = $doc->document_id;
            }


            // Software Components
            $SC = $this->db->selectCollection('SoftwareComponent')->aggregate($pipelineNoVerify);
            foreach ($SC as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE COMPONENT: SC', 'baseline' => $doc->baseline, 'status' => true, 'doc_link' => 'GitHub', 'link' => $doc->software_component,
                    'qa_name' => null, 'position' => null, 'verified_date' => null, 'validation_person' => null, 'validated_date' => null, 'verification_type' => "SOFTWARE_COMPONENT",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // software
            $URL = $this->db->selectCollection('Software')->aggregate($pipelineNoVerify);
            foreach ($URL as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE: Software', 'baseline' => $doc->baseline, 'status' => true, 'doc_link' => 'Software', 'link' => $doc->software,
                    'qa_name' => null, 'position' => null, 'verified_date' => null, 'validation_person' => null, 'validated_date' => null, 'verification_type' => "SOFTWARE",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // Test Report
            $TR = $this->db->selectCollection('TestReport')->aggregate([
                ['$match' => ['test_case_id' => $this->MongoDBObjectId($testCaseId)]],
                ['$group' => ['_id' => ['test_case_id' => '$test_case_id']]],
                [
                    '$project' => [
                        '_id' => 0,
                        'document_id' => ['$toString' => '$_id.test_case_id'],
                        'work_product' => 'TEST REPORT: TR',
                        'baseline' => null,
                        'status' => true,
                        'doc_link' => 'Test Report',
                        'link' => '/tms/reports/' . $testCaseId . '/info',
                        'qa_name' => null,
                        'position' => null,
                        'verified_date' => null,
                        'validation_person' => null,
                        'validated_date' => null,
                        'verification_type' => "TEST_REPORT",
                        "quotation" => null
                    ]
                ]

            ]);
            foreach ($TR as $doc) \array_push($projectDoc, $doc);

            // UAT
            $UAT = $this->db->selectCollection('UAT')->aggregate($pipeline);
            foreach ($UAT as $doc) {
                if ($doc->validated_date === null) {
                    $doc->validation_person = null;
                }
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'USER ACCEPTANCE TEST : UAT', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'User Acceptance Test', 'link' => '/uat/repositories/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "UAT",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // Traceability
            $Trac = $this->db->selectCollection('Traceability')->aggregate($pipeline);
            foreach ($Trac as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'TRACEABILITY: Trac', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'traceability', 'link' => '/traceability/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "TRACEABILITY_RECORD",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // SoftwareUserDocs
            $SUD = $this->db->selectCollection('SoftwareUserDocs')->aggregate($pipeline);
            foreach ($SUD as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE USER DOCUMENT: SUD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software User Document', 'link' => '/software-user-doc/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "USER_DOC",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // ProductOperationGuide
            $POG = $this->db->selectCollection('ProductOperationGuide')->aggregate($pipeline);
            foreach ($POG as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'PRODUCT OPERATION GUIDE: POG', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Production Operation Guide', 'link' => '/product-operation-guide/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "PRODUCT_OPERATION_GUIDE",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // MaintenanceDocs
            $MD = $this->db->selectCollection('MaintenanceDocs')->aggregate($pipeline);
            foreach ($MD as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'MAINTENANCE DOCUMENT: MD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Maintenance Document', 'link' => '/maintenance/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => null, 'validated_date' => $doc->validated_date, 'verification_type' => "MAINTENANCE_DOC",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // VV
            $VV = $this->db->selectCollection('VerificationValidation')->aggregate($pipeline);
            foreach ($VV as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'VERIFICATION AND VALIDATION : VV', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Verification and Validation', 'link' => 'https://snc-services.sncformer.com/dev/iPMSISO/public/index.php/api/' . $teamspaceName . '/vv/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // iCSD_Correctionregister
            $CR = $this->db->selectCollection('iCSD_Correctionregister')->aggregate($pipeline);
            foreach ($CR as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'CHANGE REQUEST: CR', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Change Request', 'link' => '/cr/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => null, 'verification_type' => "CHANGE_REQUEST",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            // AcceptRecord
            $AR = $this->db->selectCollection('AcceptRecord')->aggregate($pipeline);
            foreach ($AR as $doc) {
                $data = [
                    'document_id' => $doc->document_id, 'work_product' => 'ACCEPTANCE RECORD: AR', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Acceptance Record', 'link' => '/ar/view/' . $doc->document_id,
                    'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => null, 'verification_type' => "ACCEPT_RECORD",
                    "quotation" => $doc->quotation,
                ];
                array_push($projectDoc, $data);
            }

            return response()->json([
                "status" => "success",
                "message" => "Get project documentation successfully",
                "data" => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $projectDoc
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    // Approve verify
    public function Verification(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $validator = Validator::make($request->all(), [
                "document_id" => "required | string | min:1 | max:255",
                "is_verified" => "required | boolean",
                "pdf" => "nullable | string | min:1"
            ]);
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

            // check role
            $decoded = $jwt->decoded;
            $teamspaceName = $decoded->teamspace_name;
            $role = $decoded->role;
            if ($role != "admin") {
                return response()->json([
                    "status" => "error",
                    "meassage" => "Sorry, you are not admin",
                    "data" => []
                ]);
            }

            $pipline =
                [
                    ['$match' => ['document_id' => $this->MongoDBObjectId($request->document_id)]],
                    ['$project' => ['_id' => 0, 'document_id' => 1, 'verification_type' => 1]],
                    ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'verificationType']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verificationType', 0]], '$$ROOT']]]],
                    ['$project' => ['_id' => 0, 'document_id' => 1, 'approver_number' => 1, 'collection_name' => 1, 'verification_type' => 1]]
                ];

            $collection = $this->db->selectCollection('Approved')->aggregate($pipline);
            $data = array();
            foreach ($collection as $doc) \array_push($data, $doc);

            if (count($data) == 0) {
                return response()->json([
                    "status" => 'error',
                    "message" => 'there is no document id in the system',
                    "data" => []
                ]);
            }

            $approverNo = $data[0]->approver_number;
            $collectionName = $data[0]->collection_name;

            $isVerified = $request->is_verified;
            $documentID = $request->document_id;
            $pdf = $request->pdf;
            $createrID = $decoded->creater_by;
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            // If verify PASS.
            if ($isVerified == true) {
                $oldVersion = $this->db->selectCollection($collectionName)->find(["_id" => $this->MongoDBObjectId($documentID)]);
                $dataVersion = array();
                foreach ($oldVersion as $doc) \array_push($dataVersion, $doc);
                $version = $dataVersion[0]->version;
                $version = (string)$version + 1;
                $newVersion = substr($version, 0, 1) . ".00";

                $pipline = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$project' => ['_id' => 0]]
                ];

                $existedData = $this->db->selectCollection($collectionName)->aggregate($pipline);
                $data2 = array();
                foreach ($existedData as $doc) \array_push($data2, $doc);
                $newData = $data2[0];
                $oldVersion = $newData->version;
                $newData->version = $newVersion;
                // $newData->created_at = $timestamp;
                // $newData->updated_at = $timestamp;
                $newData->status = true;

                $verificationType = $data[0]->verification_type;
                $projectID = $newData->project_id;

                // do this only uploaded documentation
                if (in_array($verificationType, ["USER_DOC", "PRODUCT_OPERATION_GUIDE", "MAINTENANCE_DOC"])) {
                    $documentName = $dataVersion[0]->document_name;
                    // copy new file
                    $path = getcwd() . "\\..\\images\\SoftwareUserDocumentation\\";
                    // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/images/SoftwareUserDocumentation/'; // local
                    $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/SoftwareUserDocumentation/"; //server
                    // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
                    \date_default_timezone_set('Asia/Bangkok');
                    $date = date('Y-m-d H:i:s');
                    $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

                    if (is_dir($path)) {
                        $files = scandir($path);
                        $files = array_diff($files, array('.', '..'));
                        foreach ($files as $file) {
                            if (strpos($file, $oldVersion) !== false) {
                                $sourceFilePath = $path . DIRECTORY_SEPARATOR . $file;
                                $newFileName = $documentName . "_" . $newVersion . "_" . $timestamp . ".pdf";
                                $destinationFilePath = $path . DIRECTORY_SEPARATOR . $newFileName;
                                $newDocumentation = $pathUsed . $newFileName;
                                copy($sourceFilePath, $destinationFilePath);
                            }
                        }
                    }
                    $newData->documentation = $newDocumentation;
                }

                // update status in approved and document collection
                $setStatus = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["status" => $isVerified, "is_edit" => false, "updated_at" => $timestamp]]);
                $verifyresult = $this->db->selectCollection('Approved')->updateOne(["document_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_verified" => $isVerified, "verified_at" => $timestamp, "updated_at" => $timestamp]]);

                // must update document_id before send to valaidate
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                    ['$sort' => ['created_at' => -1]],
                    ['$sort' => ['version' => -1]],
                    ['$project' => ['_id' => ['$toString' => '$_id']]],
                    ['$limit' => 1],
                ];

                // create new doc version
                $upgradeVersion = $this->db->selectCollection($collectionName)->insertOne($newData);

                // get new document id and update previous document id
                $newDocumentID = $this->db->selectCollection($collectionName)->aggregate($pipline);
                $dataNewDocumentID = array();
                foreach ($newDocumentID as $doc) \array_push($dataNewDocumentID, $doc);
                $newDocumentID = $dataNewDocumentID[0]->_id;
                $updateDocID = $this->db->selectCollection('Approved')->updateOne(["document_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["document_id" => $this->MongoDBObjectId($newDocumentID), "updated_at" => $timestamp]]);
                $updateUATRepoID = $this->db->selectCollection('UATResult')->updateOne(["uat_repo_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["uat_repo_id" => $this->MongoDBObjectId($newDocumentID)]]);

                if ($verificationType == 'MAINTENANCE_DOC') {
                    $pipeline = [
                        ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                        ['$match' => ['is_edit' => ['$ne' => null]]],
                        ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                        ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                        ['$sort' => ['created_at' => -1]],
                        ['$sort' => ['version' => -1]],
                        ['$limit' => 1],
                        ['$project' => ['_id' => 1, 'baseline' => '$version', 'status' => 1, 'qa_name' => '$name_en', 'position_id' => 1, 'validation_person' => ['$arrayElemAt' => ['$costomer_contact.name', 0]]]],
                        ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                        ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                        ['$project' => [
                            '_id' => 0,
                            'document_id' => ['$toString' => '$_id'], 'baseline' => 1, 'status' => 1, 'qa_name' => 1, 'Position' => 1,
                            'verified_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'validation_person' => 1, 'validated_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']]
                        ]],
                    ];

                    $pipelineSOW = [
                        ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                        ['$match' => ['is_edit' => ['$ne' => null]]],
                        ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                        ['$sort' => ['created_at' => -1]],
                        ['$sort' => ['version' => -1]],
                        ['$limit' => 1],
                        ['$project' => [
                            '_id' => 0, 'document_id' => ['$toString' => '$_id'], 'work_product' => "STATEMENT OF WORK: SOW", 'baseline' => '$version', 'status' => 1,
                            'doc_link' => "Statement of Work",
                            'qa_name' => '$name_en', 'verified_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'validation_person' => ['$arrayElemAt' => ['$costomer_contact.name', 0]],
                            'validated_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'position_id' => 1,
                        ]],
                        ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                        ['$project' => [
                            'work_process' => [
                                'THE OBJECTIVE OF PREPARING A STATEMENT OF WORK', 'IHE INTRODUCTION OF THE PROJECT', 'THE OBJECTIVE OF PROJECT DEVELOPMENT',
                                'THE SCOPE OF DEVELOPMENT', 'THE AGREEMENT OF DEVELOPMENT', 'RISK MANAGEMENT', 'LIST OF DELIVERABLES', 'LIST OF CONTACT PEOSONS'
                            ],
                            'document_id' => 1, 'work_product' => 1, 'baseline' => 1, 'status' => 1,
                            'doc_link' => 1, 'link' => ['$concat' => ['/sow/view/', '$document_id']],
                            'qa_name' => 1, 'position' => '$Position', 'verified_date' => 1,
                            'validation_person' => 1, 'validated_date' => 1, 'verification_type' => "SOW"
                        ]]
                    ];

                    // Statement of Work
                    $SOW = $this->db->selectCollection('StatementOfWork')->aggregate($pipelineSOW);
                    $projectDoc = array();
                    foreach ($SOW as $doc) \array_push($projectDoc, $doc);

                    // Project planing
                    $PP = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipeline);
                    foreach ($PP as $doc) {
                        $data = [
                            'work_process' => ['OBJECTIVE OF PREPARING THE PROJECT MANAGEMENT PLAN', 'GUIDELINES FOR PROJECT MANAGEMENT', 'DETAIL REQUIREMENTS', 'ITEMS DELIVERED ACCORDING TO PROJECT REQUIREMENTS', 'PROJECT STRUCTURE/ROLE/RESPONSIBILTY', 'PROJECT SCHEDULE DETAILS', 'EQUIPMENT AND APPLIANCES NEEDED FOR THE PROJECT', 'COST ESTIMATION', 'RISK MANAGEMENT', 'PROJECT INFRASTRUCTURE/REPOSITORY & QUALITY MANAGEMENT SYSTEM'],
                            'document_id' => $doc->document_id, 'work_product' => 'PROJECT PLANING: PP', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Project Planing', 'link' => '/projects-planning/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "PROJECT_PLAN"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Software requirement specification
                    $SRS = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($pipeline);
                    foreach ($SRS as $doc) {
                        $data = [
                            'work_process' => ['THE INTRODUCTION OF THE PROJECT', 'SOFTWARE REQUIREMENTS', 'SYSTEM REQUIREMENTS'],
                            'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE REQUIREMENT SPECIFICATION: SRS', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software Requirement Specification', 'link' => '/srs/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "SRS"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Software requirement specification
                    $SD = $this->db->selectCollection('SoftwareDesign')->aggregate($pipeline);
                    foreach ($SD as $doc) {
                        $data = [
                            'work_process' => ['SYSTEM AND SOFTWARE DESIGN OVERVIEW', 'ARCHITECHTURE CONCEPT DESIGN', 'USE CASE DIAGRAM', 'ENTITY RELATIONSHIP DIAGRAM', 'USER INTERFACE DESIGN'],
                            'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE DESIGN: SD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software Design', 'link' => '/software-design/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "DESIGN"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Test Cases
                    $TC = $this->db->selectCollection('TestCases')->aggregate($pipeline);
                    foreach ($TC as $doc) {
                        $data = [
                            'work_process' => ['TEST CASES AND TEST PROCEDURES'],
                            'document_id' => $doc->document_id, 'work_product' => 'TEST CASES: TC', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Test Cases', 'link' => '/tms/repositories/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "TEST_CASES"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Software Components
                    $SC = $this->db->selectCollection('SoftwareComponent')->aggregate($pipeline);
                    foreach ($SC as $doc) {
                        $data = [
                            'work_process' => ['SOURCE CODE'],
                            'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE COMPONENT: SC', 'baseline' => $doc->baseline, 'doc_link' => 'GitHub', 'link' => '/software-component/view/' . $doc->document_id, 'verification_type' => "SOFTWARE_COMPONENT"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // URL
                    $URL = $this->db->selectCollection('Software')->aggregate($pipeline);
                    foreach ($URL as $doc) {
                        $data = [
                            'work_process' => ['URL'],
                            'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE: Software', 'baseline' => $doc->baseline, 'doc_link' => 'Software', 'link' => '/software/view/' . $doc->document_id, 'verification_type' => "SOFTWARE"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Test Report
                    $TR = $this->db->selectCollection('TestReport')->aggregate($pipeline);
                    foreach ($TR as $doc) {
                        $data = [
                            'work_process' => ['TEST REPORT'],
                            'document_id' => $doc->document_id, 'work_product' => 'TEST REPORT: TR', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Test Report', 'link' => 'tms/reports/' . $doc->document_id . '/info',
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "TEST_REPORT"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // UAT
                    $UAT = $this->db->selectCollection('UAT')->aggregate($pipeline);
                    foreach ($UAT as $doc) {
                        $data = [
                            'work_process' => ['UAT'],
                            'document_id' => $doc->document_id, 'work_product' => 'USER ACCEPTANCE TEST : UAT', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'User Acceptance Test', 'link' => '/uat/repositories/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "UAT"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // Traceability
                    $Trac = $this->db->selectCollection('Traceability')->aggregate($pipeline);
                    foreach ($Trac as $doc) {
                        $data = [
                            'work_process' => ['SOFTWARE REQUIREMENTS SPECIFICATION', 'FUNCTIONAL REQUIREMENTS', 'SOFTWARE DESIGN', 'SOFTWARE COMPONENT', 'TEST CASE ID', 'TEST CASE NAME'],
                            'document_id' => $doc->document_id, 'work_product' => 'TRACEABILITY: Trac', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'traceability', 'link' => '/traceability/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "TRACEABILITY_RECORD"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // SoftwareUserDocs
                    $SUD = $this->db->selectCollection('SoftwareUserDocs')->aggregate($pipeline);
                    foreach ($SUD as $doc) {
                        $data = [
                            'work_process' => ['SOFTWARE USER DOCUMENT'],
                            'document_id' => $doc->document_id, 'work_product' => 'SOFTWARE USER DOCUMENT: SUD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Software User Document', 'link' => '/software-user-doc/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "USER_DOC"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // ProductOperationGuide
                    $POG = $this->db->selectCollection('ProductOperationGuide')->aggregate($pipeline);
                    foreach ($POG as $doc) {
                        $data = [
                            'work_process' => ['PEODUCT OPERATION GUIDE'],
                            'document_id' => $doc->document_id, 'work_product' => 'PRODUCT OPERATION GUIDE: POG', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Production Operation Guide', 'link' => '/product-operation-guide/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "PRODUCT_OPERATION_GUIDE"
                        ];
                        array_push($projectDoc, $data);
                    }

                    // MaintenanceDocs
                    $MD = $this->db->selectCollection('MaintenanceDocs')->aggregate($pipeline);
                    foreach ($MD as $doc) {
                        $data = [
                            'work_process' => ['MAINTENANCE DOCUMENT'],
                            'document_id' => $doc->document_id, 'work_product' => 'MAINTENANCE DOCUMENT: MD', 'baseline' => $doc->baseline, 'status' => $doc->status, 'doc_link' => 'Maintenance Document', 'link' => '/maintenance/view/' . $doc->document_id,
                            'qa_name' => $doc->qa_name, 'position' => $doc->Position, 'verified_date' => $doc->verified_date, 'validation_person' => $doc->validation_person, 'validated_date' => $doc->validated_date, 'verification_type' => "MAINTENANCE_DOC"
                        ];
                        array_push($projectDoc, $data);
                    }

                    //  change verified by to  real admin
                    $vv = $this->db->selectCollection('VerificationValidation')->insertOne(['project_id' => $this->MongoDBObjectId($projectID), 'document' => $projectDoc, 'version' => '0.01', 'is_edit' => false, 'status' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp, 'verified_by' => $this->MongoDBObjectId('65f8ff160ef792b118003f87')]);
                    $vvID = $vv->getInsertedId();
                    $addApproved = $this->db->selectCollection('Approved')->insertOne(['project_id' => $this->MongoDBObjectId($projectID), 'creator_id' => $this->MongoDBObjectId('65f908260ef792b118003f9e'), 'document_id' => $vvID, 'verification_type' => 'VERIFICATION_VALIDATION', 'is_verified' => null, 'verifed_at' => null, 'verified_by' => $this->MongoDBObjectId('65f8ff160ef792b118003f87'), 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                }

                // if need validate, send email
                if ($approverNo  == 2) {
                    // send email to customer validation
                    $pipline = [
                        ['$match' => ['document_id' => $this->MongoDBObjectId($newDocumentID)]],
                        ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                        ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                        [
                            '$project' => [
                                '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'customer_contact' => 1, 'project_name' => 1,
                                'customerEmail' => ['$arrayElemAt' => ['$customer_contact.email', 0]],
                                'customerName' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                                'verification_type' => 1,
                                'project_type' => 1,
                                'sap_code' => 1,
                                'version' => 1,
                            ]
                        ]
                    ];
                    $project = $this->db->selectCollection('Approved')->aggregate($pipline);
                    $dataProject = array();
                    foreach ($project as $doc) \array_push($dataProject, $doc);
                    $projectID = $dataProject[0]->project_id;
                    $projectName = $dataProject[0]->project_name;
                    $customerContact = $dataProject[0]->customer_contact;
                    $customer = $dataProject[0]->customer_contact[0]->name;
                    $customerEmail  = $dataProject[0]->customerEmail;
                    $customerName = $dataProject[0]->customerName;
                    $verificationType = $dataProject[0]->verification_type;
                    $projectType = $dataProject[0]->project_type;
                    $sapCode = $dataProject[0]->sap_code;
                    $version = $dataProject[0]->version;

                    $step = null;
                    $documentName = null;
                    $link = null;
                    $topic = null;
                    if ($verificationType == "PROJECT_PLAN") {

                        $step = 1;
                        $documentName = "PROJECT PLAN (PP)";
                        $link = "https://icode.snc-code.com/pp/?document_id=" . $newDocumentID;
                        $topic = "PROJECT_PLAN";
                        $pdfFile = null;
                        if ($pdf != null && str_starts_with($pdf, 'data:application/pdf;base64,')) {
                            $path = getcwd() . "\\..\\images\\Quotation\\";
                            if (!is_dir($path)) mkdir($path, 0777, true);
                            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/images/Quotation/'; // local
                            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/Quotation/"; //server
                            $fileName =  $projectID . "_" . $timestamp . ".pdf";

                            //save file to server
                            $folderPath = $path  . "\\";
                            if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                            file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $pdf)));
                            $quotationPDF = $pathUsed . $fileName;
                            $pdfFile = $quotationPDF;
                        }
                        $savePDF = $this->db->selectCollection("ProjectsPlaning")->updateOne(["_id" => $this->MongoDBObjectId($newDocumentID)], ['$set' => ["quotation" => $pdfFile]]);
                    } else if ($verificationType == "SRS") {
                        $step = 2;
                        $documentName = "SOFTWARE REQUIREMENT SPECIFICATIONS (SRS)";
                        $link = "https://icode.snc-code.com/srs?document_id=" . $newDocumentID;
                        $topic = "SRS";
                        $pdfFile = null;
                    } else if ($verificationType == "UAT") {
                        $step = 3;
                        $documentName = "UAT";
                        $link = "https://icode.snc-code.com/uat?document_id=" . $newDocumentID;
                        $topic = "UAT";
                        $pdfFile = null;
                    }

                    $addEmail = $this->db->selectCollection('EmailSending')->insertOne([
                        'customer_email' => $customerEmail,
                        'customer_name' => $customerName,
                        'project_name' => $projectName,
                        'topic' => $topic,
                        'step' => $step,
                        'link' => $link,
                        'pdf' => $pdfFile,
                        'is_sent' => false,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ]);
                }

                $messageResponse  = "Verified successfully !!";
            }

            // If verify FAIL.
            else if ($isVerified == false) {
                // update status in approved and document collection
                $setStatus = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["status" => $isVerified, "is_edit" => true, "updated_at" => $timestamp]]);
                $verifyresult = $this->db->selectCollection('Approved')->updateOne(["document_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_verified" => $isVerified, "verified_at" => $timestamp, "updated_at" => $timestamp]]);

                $messageResponse  = "Declinde successfully !!";
            }

            return response()->json([
                "status" => "success",
                "message" => $messageResponse,
                "data" => [$verifyresult->getModifiedCount()],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    // Approve validate
    public function Validation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                // "token" => "required | string | min:1",
                "document_id" => "  required | string | min:1",
                "is_validated" => "required | boolean",
            ]);
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

            $isValidated = $request->is_validated;

            // $token = "Bearer ".$request->token;
            // $jwt = $this->jwtUtils->verifyToken($token);
            // $decoded = $jwt->decoded;

            $documentID = $request->document_id;
            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $pipline =
                [
                    ['$match' => ['document_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'verificationType']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verificationType', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'UAT', 'localField' => 'document_id', 'foreignField' => '_id', 'as' => 'UAT']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$UAT', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0, 'document_id' => 1, 'approver_number' => 1, 'collection_name' => 1, 'verification_type' => 1,
                            'varidated_by' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                            'customer_contact' => 1,
                            'customer_email' => ['$arrayElemAt' => ['$customer_contact.email', 0]],
                            'customer_name' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                            'project_name' => 1, 'project_id' => ['$toString' => '$project_id']
                        ]
                    ]
                ];


            $collection = $this->db->selectCollection('Approved')->aggregate($pipline);
            $data = array();
            foreach ($collection as $doc) \array_push($data, $doc);

            $collectionName = $data[0]->collection_name;
            $validatedBy = $data[0]->varidated_by;
            $customer = $data[0]->customer_contact[0]->name;
            $customerEmail = $data[0]->customer_email;
            $customerName = $data[0]->customer_name;
            $projectName = $data[0]->project_name;
            $verificationType = $data[0]->verification_type;
            $projectID = $data[0]->project_id;

            // If validate PASS.
            if ($isValidated == true) {

                // update status in approved and document collection
                $setStatus = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["status" => $isValidated, "is_edit" => false, "updated_at" => $timestamp]]);
                $updateApproved = $this->db->selectCollection('Approved')->updateOne(["document_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_validated" => $isValidated, "validated_at" => $timestamp, "validated_by" => $validatedBy, "updated_at" => $timestamp]]);

                $messageResponse  = "Validated successfully !!";
            }

            // If validate FAIL
            else if ($isValidated == false) {
                // update status in approved and document collection -> if fail can edit document
                $setStatus = $this->db->selectCollection($collectionName)->updateOne(["_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["status" => $isValidated, "is_edit" => true, "updated_at" => $timestamp]]);
                $verifyresult = $this->db->selectCollection('Approved')->updateOne(["document_id" => $this->MongoDBObjectId($documentID)], ['$set' => ["is_validated" => $isValidated, "validated_at" => $timestamp, "validated_by" => $validatedBy, "updated_at" => $timestamp]]);

                $messageResponse  = "Declinde successfully !!";
            }

            if ($verificationType == "PROJECT_PLAN") {
                $link = "https://icode.snc-code.com/pp/view?document_id=" . $documentID;
            } else if ($verificationType == "SRS") {
                $link = "https://icode.snc-code.com/srs/view?document_id=" . $documentID;
            } else {
                $link = "https://icode.snc-code.com/uat/view?document_id=" . $documentID;
            }


            // $ccEmails = ['nathaphart@sncformer.com', 'danaithon@sncformer.com', 'rattaphong@sncformer.com'];
            // Mail::to($customerEmail)
            // ->cc($ccEmails)
            // ->send(new ValidationResponse($isValidated, $customerName, $projectName, $link));

            $addEmail = $this->db->selectCollection('EmailSending')->insertOne([
                'customer_email' => $customerEmail,
                'is_validated' => $isValidated,
                'customer_name' => $customerName,
                'project_name' => $projectName,
                'link' => $link,
                'is_sent' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]);

            return response()->json([
                "status" => "success",
                "message" => $messageResponse,
                "data" => [],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [POST] /approved/add-comment-verified
    public function addCommetVerified(Request $request)
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
                'verification_id'           => 'required | string | min:1 | max:255',
                'comments'                  => ['nullable', 'array'],
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

            //! check data verified id
            $filter1 = ["_id" => $this->MongoDBObjectId($request->verification_id)];
            $options1 = ["projection" => ["_id" => 0, "verification_id" => ['$toString' => '$_id']]];

            $chkProject = $this->db->selectCollection("Approved")->find($filter1, $options1);
            $dataChk1 = array();
            foreach ($chkProject as $doc) \array_push($dataChk1, $doc);
            if (\count($dataChk1) == 0)
                return response()->json(["status" => "error", "message" => "verification id dosen't exsit", "data" => []], 500);

            //! check data verified id


            $verificationID      = $request->verification_id;
            $projectID          = $request->project_id;
            $comments           = $request->comments;
            $creatorID           = $request->creator_id;
            // $creatorID           = $decoded -> creater_by;

            $dataComments = [];
            foreach ($comments as $info) \array_push($dataComments, $info);

            $dataListComments = [];
            for ($i = 0; $i < count($comments); $i++) {
                $list = [
                    "creator_id"    => $this->MongoDBObjectId($dataComments[$i]["creator_id"]),
                    "comment"       => ($dataComments[$i]["comment"]),
                    "comment_at"    => $timestamp,
                ];
                array_push($dataListComments, $list);
            };

            $document = [
                "verification_id"           => $this->MongoDBObjectId($verificationID),
                // "document_id"               => $this->MongoDBObjectId($documentID),
                "project_id"                => $this->MongoDBObjectId($projectID),
                "creator_id"                => $this->MongoDBObjectId($creatorID),

                "comments"                  => $dataListComments,

                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,
            ];

            $result = $this->db->selectCollection('Comments')->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Sent new comment successfully !!",
                "data" => [$result->getInsertedCount()]
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

    //* [GET] /approved/comment-verified
    public function getCommet(Request $request)
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

            $pipeline = [['$lookup' => ['from' => 'Comments', 'localField' => '_id', 'foreignField' => 'verification_id', 'as' => 'Comments']], ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Comments', 0]], '$$ROOT']]]], ['$project' => ['_id' => 0, 'verification_id' => '$_id', 'project_id' => '$project_id', 'creator_id' => '$creator_id', 'document_id' => '$document_id', 'approved_id' => '$approved_id', 'is_verified' => 1, 'is_validate' => 1, 'verification_type' => 1, 'title' => 1, 'description' => 1, 'Comments' => '$comments']], ['$project' => ['_id' => 0, 'verification_id' => ['$toString' => '$verification_id'], 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'], 'document_id' => ['$toString' => '$document_id'], 'approved_id' => ['$toString' => '$approved_id'], 'is_verified' => 1, 'is_validate' => 1, 'verification_type' => 1, 'title' => 1, 'description' => 1, 'Comments' => ['$map' => ['input' => '$Comments', 'as' => 'resp', 'in' => ['creator_id' => ['$toString' => '$$resp.creator_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]]]]];

            $result = $this->db->selectCollection('Approved')->aggregate($pipeline);

            $data = [];
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all comment successfully !!",
                "data" => $data
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

    // print v and v document
    public function printVandV(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $validator = Validator::make($request->all(), [
                "document_id" => 'required | string | min:1',
            ]);
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
            $documentID = $request->document_id;
            $project = $this->db->selectCollection('VerificationValidation')->findOne(['_id' => $this->MongoDBObjectId($documentID)]);
            $projectID = $project['project_id'];


            $cover = [
                ['$match' => ['project_id' => $projectID]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => [
                    'from' => 'Software', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Software',
                    'pipeline' => [['$group' => ['_id' => ['project_id' => '$project_id'], 'software_version' => ['$last' => '$version']]]]
                ]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Software', 0]], '$$ROOT']]]],
                ['$project' => [
                    "project_id" => ['$toString' => '$project_id'],
                    "sap_code" => 1,
                    "project_type" => 1,
                    "product" => ['$concat' => ['$sap_code', ' ', '$project_type']],
                    'project_name' => 1,
                    'customer_name' => 1,
                    'check_date' => null,
                    "verified_by" => 'Nathaphart Bangkerd',
                    "version" => 1,
                    'software_version' => '$software_version',
                    "conductor" => '$name_en',
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "verified_at" => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]]
            ];
            $userCov = $this->db->selectCollection("VerificationValidation")->aggregate($cover);
            $dataCover = array();

            // create revise history
            $reviseHistory = array();
            foreach ($userCov as $cov) {
                if (str_ends_with((string)$cov->version, '.00')) {
                    $revise = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => null, "created_at" => null],
                        "approver" => ["approver" => $cov->verified_by, "verified_at" => $cov->verified_at],
                        "description" => "Approved",
                    ];
                } else if ((string)$cov->version == '0.01') {
                    $revise = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "description" => "Created",
                    ];
                } else {
                    $revise = [
                        "project_id" => $cov->project_id,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "description" => "Edited",
                    ];
                }
                array_push($reviseHistory, $revise);
            }

            foreach ($userCov as $cov) {
                $reportCover = [
                    "product" => $cov->product,
                    "sap_code" => $cov->sap_code,
                    "project_type" => $cov->project_type,
                    "project_name" => $cov->project_name,
                    "customer_name" => $cov->customer_name,
                    "check_date" => $cov->check_date,
                    "qa_name" => $cov->verified_by,
                    "version" => $cov->version,
                    "software_version" => $cov->software_version,
                    "approved_date" => $cov->verified_at,
                    "revision_history" => $reviseHistory,
                ];
                array_push($dataCover, $reportCover);
            }

            $pipeline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                ['$project' => [
                    '_id' => 0, 'document_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'document' => 1,
                    'version' => 1, 'status' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]]
            ];

            $printVV = $this->db->selectCollection('VerificationValidation')->aggregate($pipeline);
            $printVVD = array();
            foreach ($printVV as $doc) \array_push($printVVD, $doc);

            return response()->json([
                'status' => 'success',
                'message' => 'Get data successfully !!',
                'data' => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $printVVD[0]->document,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    public function GetDocAtEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "document_id" => 'required | string | min:1',
                "verification_type" => 'required | string | min:1',
            ]);
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

            $documentID = $request->document_id;
            $verificationType = $request->verification_type;

            if ($verificationType == "PROJECT_PLAN") {
                $pipelineUserDoc = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0, 'is_validated' => 1,
                            'sap_code' => 1, 'project_id' => ['$toString' => '$project_id'], 'customer' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                            'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                            'approved_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'creator_name' => '$name_en',
                            'system_requirement' => '$objective_of_project', 'software_requirement' => 1, 'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'item', 'in' => ['account_id' => ['$toString' => '$$item.account_id'], 'role_id' => ['$toString' => '$$item.role_id'],]]],
                            'equipments' => 1, 'cost_estimation' => 1, 'cost_estimation' => 1, 'objective_of_project' => 1, 'end_date' => 1,
                            'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        ]
                    ]
                ];
                $userDoc = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipelineUserDoc);
                $dataUserDoc = array();
                foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);

                $roleName = array();
                foreach ($dataUserDoc[0]->responsibility as $value) {
                    $roleQuery = $this->db->selectCollection('RoleResponsibility')->find(['_id' => $this->MongoDBObjectId($value['role_id'])]);
                    foreach ($roleQuery as $role) {
                        $roleName = $role->name;
                        $value['role_name'] = $roleName;
                    }
                    $accountQuery = $this->db->selectCollection('Accounts')->find(['_id' => $this->MongoDBObjectId($value['account_id'])]);
                    foreach ($accountQuery as $account) {
                        $value['email'] = $account->username;
                        $value['picture'] = $account->picture;
                        $value['name_en'] = $account->name_en;
                        $value['position_id'] = $account->position_id;
                    }

                    $positionQuery = $this->db->selectCollection('Positions')->find(['_id' => $this->MongoDBObjectId($value['position_id'])]);
                    foreach ($positionQuery as $position) {
                        $value['position_name'] = $position->Position;
                    }
                }

                $projectID = $dataUserDoc[0]->project_id;
                $customer = $dataUserDoc[0]->customer;
                $isValidated = $dataUserDoc[0]->is_validated;

                $reviseHistory = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                    ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork',  'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
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
                $userCov = $this->db->selectCollection('ProjectsPlaning')->aggregate($reviseHistory);
                $dataCover = array();

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
                                "version" => $cov->version,
                                "conductor" => ["conductor" => null, "created_at" => null],
                                "approver" => ["approver" => $approver, "verified_at" =>  $verified_at],
                                "reviewer" => ["reviewer" => $reviewer, "validated_at" => $validated_at],
                                "description" => "Approved",
                            ];
                            array_push($dataCover, $coverData);
                        }
                    } else if ((string)$cov->version == '0.01') {
                        $coverData = [
                            "version" => $cov->version,
                            "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                            "approver" => ["approver" => null, "verified_at" => null],
                            "reviewer" => ["reviewer" => null, "validated_at" => null],
                            "description" => "Created",
                        ];
                        array_push($dataCover, $coverData);
                    } else {
                        $coverData = [
                            "version" => $cov->version,
                            "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                            "approver" => ["approver" => null, "verified_at" => null],
                            "reviewer" => ["reviewer" => null, "validated_at" => null],
                            "description" => "Edited",
                        ];
                        array_push($dataCover, $coverData);
                    }
                };

                $report = [
                    "project_plan_id" => $documentID,
                    "project_id" => $projectID,
                    "is_validated" => $isValidated,
                    "customer" => $customer,
                    "report_cover" => $dataCover,
                    "report_detail" => $dataUserDoc,
                ];
            } else if ($verificationType == "SRS") {
                $pipelineUserDoc = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'customer' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                            'sap_code' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                            'approved_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'creator_name' => '$name_en', 'is_validated' => 1,
                            'introduction_of_project' => 1, 'list_of_introduction' => 1,
                            'system_requirement' => '$objective_of_project', 'software_requirement' => 1, 'functionality' => ['$arrayElemAt' => ['$ProjectsPlaning.software_requirement', 0]],
                            'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        ]
                    ],
                    [
                        '$project' => [
                            '_id' => 0, 'project_id' => 1, 'customer' => 1, 'is_validated' => 1,
                            'sap_code' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                            'approved_date' => 1, 'creator_name' => 1, 'introduction_of_project' => 1, 'list_of_introduction' => 1,
                            'software_requirement' => 1,
                            // 'software_requirement'=>['$map' => ['input' => ['$range' => [0, ['$min' => [['$size' => '$software_requirement'], ['$size' => '$functionality']]]]], 'as' => 'index', 'in' => ['$mergeObjects' => [['$arrayElemAt' => ['$software_requirement', '$$index']], ['$arrayElemAt' => ['$functionality', '$$index']]]]]],
                            'system_requirement' => 1, 'created_at' => 1, 'updated_at' => 1
                        ]
                    ]
                ];
                $userDoc = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($pipelineUserDoc);
                $dataUserDoc = array();
                foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);
                $projectID = $dataUserDoc[0]->project_id;
                $customer = $dataUserDoc[0]->customer;
                $isValidated = $dataUserDoc[0]->is_validated;

                $reviseHistory = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                    ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork',  'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
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
                                "version" => $cov->version,
                                "conductor" => ["conductor" => null, "created_at" => null],
                                "approver" => ["approver" => $approver, "verified_at" =>  $verified_at],
                                "reviewer" => ["reviewer" => $reviewer, "validated_at" => $validated_at],
                                "description" => "Approved",
                            ];
                            array_push($dataCover, $coverData);
                        }
                    } else if ((string)$cov->version == '0.01') {
                        $coverData = [
                            "version" => $cov->version,
                            "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                            "approver" => ["approver" => null, "verified_at" => null],
                            "reviewer" => ["reviewer" => null, "validated_at" => null],
                            "description" => "Created",
                        ];
                        array_push($dataCover, $coverData);
                    } else {
                        $coverData = [
                            "version" => $cov->version,
                            "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                            "approver" => ["approver" => null, "verified_at" => null],
                            "reviewer" => ["reviewer" => null, "validated_at" => null],
                            "description" => "Edited",
                        ];
                        array_push($dataCover, $coverData);
                    }
                };

                $report = [
                    "software_req_id" => $documentID,
                    "project_id" => $projectID,
                    "is_validated" => $isValidated,
                    "customer" => $customer,
                    "report_cover" => $dataCover,
                    "report_detail" => $dataUserDoc,
                ];
            } else if ($verificationType == "UAT") {
                $pipelineUserDoc = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork',  'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0,
                            'creator_name' => '$name_en',
                            'topics' => 1,
                            'tester' => ['$arrayElemAt' => ['$customer_contact.name', 0]],
                            'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        ]
                    ]
                ];
                $userDoc = $this->db->selectCollection('UAT')->aggregate($pipelineUserDoc);
                $dataUserDoc = array();
                foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);

                $reviseHistory = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork',  'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    ['$lookup' => [
                        'from' => 'Software', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Software',
                        'pipeline' => [['$group' => ['_id' => ['project_id' => '$project_id'], 'software_version' => ['$last' => '$version']]]]
                    ]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Software', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0,
                            'sap_code' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'version' => 1,
                            'software_version' => '$software_version',
                            'approved_date' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                            'creator_name' => '$name_en', 'introduction_of_project' => 1, 'system_requirement' => 1, 'software_requirement' => 1,
                        ]
                    ]
                ];
                $userCov = $this->db->selectCollection('UAT')->aggregate($reviseHistory);
                $dataCover = array();
                foreach ($userCov as $value) array_push($dataCover, $value);

                $report = [
                    "uat_repo_id" => $documentID,
                    "report_cover" => $dataCover,
                    "report_detail" => $dataUserDoc,
                ];
            } else {
                $UATCover = [
                    ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0, 'conductor' => ['conductor' => '$name_en', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]], 'reviewer' => ['reviewer' => ['$arrayElemAt' => ['$customer_contact.name', 0]], 'validated_at' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']]],
                            'description' => "Created UAT", 'date' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d']],
                            'verified_by' => 1, 'verified_at' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                        ]
                    ],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            '_id' => 0, 'date' => 1,
                            'conductor' => 1, 'reviewer' => 1, 'approver' => ['approver' => '$name_en', 'verified_at' => '$verified_at'], 'description' => 1,
                        ]
                    ]
                ];
                $userCov = $this->db->selectCollection('UAT')->aggregate($UATCover);
                $dataCover = array();
                foreach ($userCov as $value) array_push($dataCover, $value);

                $UATResult = [
                    ['$match' => ['uat_repo_id' => $this->MongoDBObjectId($documentID)]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$sort' => ['created_at' => -1]], ['$limit' => 1]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'UAT', 'localField' => 'uat_repo_id', 'foreignField' => '_id', 'as' => 'UAT']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$UAT', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Approved', 'localField' => 'uat_repo_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                    ['$lookup' => [
                        'from' => 'Software', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'Software',
                        'pipeline' => [['$group' => ['_id' => ['project_id' => '$project_id'], 'software_version' => ['$last' => '$version']]]]
                    ]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Software', 0]], '$$ROOT']]]],
                    ['$project' => [
                        '_id' => 0, 'project_id' => ['$toString' => '$project_id'],
                        'sap_code' => 1, 'project_type' => 1, 'product' => ['$concat' => ['$sap_code', ' ', '$project_type']],
                        'project' => '$project_name', 'customer' => '$customer_name',
                        'version' => '$version', 'software_version' => '$software_version', 'reviewed_date' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d']],
                        'reviewer' => [
                            '$arrayElemAt' => ['$customer_contact.name', 0],
                        ], 'uat_list' => [
                            '$map' => [
                                'input' => '$testing_results',
                                'as' => 'uat',
                                'in' => [
                                    'uat_code' => '$$uat.uat_code',
                                    'req_code' => '$$uat.req_code',
                                    'topic' => '$$uat.topic_description',
                                    'is_accepted' => '$$uat.is_accepted',
                                ],
                            ],
                        ],
                    ]]
                ];
                $userResult = $this->db->selectCollection('UATResult')->aggregate($UATResult);
                $dataResult = array();
                foreach ($userResult as $value) array_push($dataResult, $value);
                $project_id = $dataResult[0]->project_id;
                $customer = $dataResult[0]->customer;

                $report = [
                    "uat_repo_id" => $documentID,
                    'project_id' => $project_id,
                    "customer" => $customer,
                    "report_cover" => $dataCover,
                    "report_detail" => $dataResult,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get data successfully !!',
                'data' => $report,
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
