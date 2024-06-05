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

class SoftwareDesignController extends Controller
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

    //! [POST] /software-design/create
    public function UploadDesign(Request $request)
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
                "documentation" => "required | string | min:1"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $decoded = $jwt->decoded;
            $creatorID = $decoded->creater_by;
            $projectID = $request->project_id;
            $documentName = $request->document_name;
            $documentation = $request->documentation;

            $pipline = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1]
            ];

            $userDocVersion = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
            $dataUserDocument = array();
            foreach ($userDocVersion as $doc) \array_push($dataUserDocument, $doc);

            // Upload file first time
            if (count($dataUserDocument) == 0) {
                $version = '0.01';
            } else {
                $version = $dataUserDocument[0]->version;
                $version = (string)$version + 1;
                $version = substr($version, 0, 1) . ".00";
            }

            $timestamp = $this->MongoDBUTCDatetime(time() * 1000);

            $path = getcwd() . "\\..\\images\\SoftwareDesign\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/SoftwareDesign/"; //server
            $fileName = $documentName . "_" . $version . "_" . $timestamp . ".pdf";

            if (str_starts_with($documentation, 'data:application/pdf;base64,')) {
                //save file to server
                $folderPath = $path  . "\\";
                if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentation)));
                $softwareUserDoc = $pathUsed . $fileName;
            }

            $upload = $this->db->selectCollection("SoftwareDesign")->insertOne([
                "project_id" => $this->MongoDBObjectId($projectID),
                "creater_by" => $this->MongoDBObjectId($creatorID),
                "document_name" => $documentName,
                "documentation" => $softwareUserDoc,
                "version" => $version,
                "is_edit" => null,
                "status"  => null,
                "created_at" => $timestamp,
                "updated_at" => $timestamp,
            ]);

            return response()->json([
                "status" => "success",
                "message" => "Document uploaded successfully",
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

    // Get ducuments for each project_id
    public function GetSoftwareDesign(Request $request)
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
                "project_id" => "required | string | min:1 | max:255"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $projectID = $request->project_id;

            $pipline = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => 1]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'creater_by', 'foreignField' => '_id', 'as' => 'Users']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Users', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "document_name" => 1,
                    "documentation" => 1,
                    "version" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "is_edit" => 1,
                    "creater_by" => ['$toString' => '$creater_by'],
                    "name" => 1,
                ]],
            ];

            $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) {
                // return response() -> json((string)$doc->version, '.00');
                if (str_ends_with((string)$doc->version, '.00') && $doc->is_edit === false) {
                    $status = true;
                } else if (!str_ends_with((string)$doc->version, '.00') && $doc->is_edit === false) {
                    $status = false;
                } else if ($doc->is_edit === null) {
                    $status = null;
                }
                $dataShow = [
                    "project_id" => $doc->project_id,
                    "document_id" => $doc->_id,
                    "document_name" => $doc->document_name,
                    "documentation" => $doc->documentation,
                    "version" => $doc->version,
                    "created_at" => $doc->created_at,
                    "is_edit" => $doc->is_edit,
                    "status" => $status, // "approved", "rejected", "pending
                    "created_by" => $doc->creater_by,
                    "creator_name" => $doc->name,
                ];
                array_push($dataUserDoc, $dataShow);
            };


            return response()->json([
                "status" => "success",
                "message" => "Document found",
                "data" => $dataUserDoc
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    // Get individual document
    // public function GetIndividualDoc(Request $request)
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

    //         $validator = Validator::make($request->all(), [
    //             "document_id" => "required | string | min:1 | max:255"
    //         ]);
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 "data" => [],
    //             ], 400);
    //         }

    //         $documentID = $request->document_id;
    //         $pipline = [
    //             ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
    //             ['$lookup' => ['from' => 'Users', 'localField' => 'creater_by', 'foreignField' => '_id', 'as' => 'Users']],
    //             ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Users', 0]], '$$ROOT']]]],
    //             ['$project' => [
    //                 "_id" => ['$toString' => '$_id'],
    //                 "project_id" => ['$toString' => '$project_id'],
    //                 "document_name" => 1,
    //                 "documentation" => 1,
    //                 "version" => 1,
    //                 "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
    //                 "is_edit" => 1,
    //                 "creater_by" => ['$toString' => '$creater_by'],
    //                 "name" => 1,
    //             ]],
    //         ];

    //         $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
    //         $dataUserDoc = array();
    //         foreach ($userDoc as $doc) {
    //             // return response() -> json((string)$doc->version, '.00');
    //             if (str_ends_with((string)$doc->version, '.00') && $doc->is_edit === false) {
    //                 $status = true;
    //             } else if (!str_ends_with((string)$doc->version, '.00') && $doc->is_edit === false) {
    //                 $status = false;
    //             } else if ($doc->is_edit === null) {
    //                 $status = null;
    //             }
    //             $dataShow = [
    //                 "project_id" => $doc->project_id,
    //                 "document_id" => $doc->_id,
    //                 "document_name" => $doc->document_name,
    //                 "documentation" => $doc->documentation,
    //                 "version" => $doc->version,
    //                 "created_at" => $doc->created_at,
    //                 "is_edit" => $doc->is_edit,
    //                 "status" => $status, // "approved", "rejected", "pending
    //                 "created_by" => $doc->creater_by,
    //                 "creator_name" => $doc->name,
    //             ];
    //             array_push($dataUserDoc, $dataShow);
    //         };

    //         return response()->json([
    //             "status" => "success",
    //             "message" => "Document found",
    //             "data" => $dataUserDoc
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             "data" => [],
    //         ], 500);
    //     }
    // }

    // Edit document
    public function EditSoftwareDesign(Request $request)
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

            $validator = Validator::make($request->all(), [
                "software_design_id" => "required | string | min:1 | max:255",
                "documentation" => "required | string | min:1"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $documentID = $request->software_design_id;
            $copyData = $this->db->selectCollection("SoftwareDesign")->find(["_id" => $this->MongoDBObjectId($documentID)]);
            $dataCopy = iterator_to_array($copyData);

            if (empty($dataCopy)) {
                return response()->json([
                    "status" => "error",
                    "message" => "software_design ID not found",
                    "data" => []
                ], 404);
            }

            $isEdit = $dataCopy[0]->is_edit;
            $projectID = $dataCopy[0]->project_id;
            $documentName = $dataCopy[0]->document_name;
            $version = $dataCopy[0]->version;
            $documentation = $request->documentation;

            // if edit is false, cannot edit
            if ($isEdit === false) {
                return response()->json([
                    "status" => "error",
                    "message" => "Document cannot be edited",
                    "data" => []
                ], 400);
            }

            $timestamp = $this->MongoDBUTCDatetime(time() * 1000);

            $path = getcwd() . "\\..\\images\\SoftwareDesign\\";
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }

            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/SoftwareDesign/";
            $fileName = $documentName . "_" . $version . "_" . $timestamp . ".pdf";

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

                // save file to server
                $folderPath = $path . "\\";
                if (!is_dir($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }
                file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentation)));
                $softwareUserDoc = $pathUsed . $fileName;
            }

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => 1,
                    "creater_by" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                ]]
            ];
            $checkEdit = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
            $checkEditData = array();
            foreach ($checkEdit as $doc) \array_push($checkEditData, $doc);

            // $update = [
            //     '$set' => [
            //         "documentation" => $softwareUserDoc,
            //         "updated_at" => $timestamp
            //     ]
            // ];
            // if is_edit is not false, can edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status === null) {
                $updateDocument = $this->db->selectCollection("SoftwareDesign")->updateOne(
                    ['_id' => $this->MongoDBObjectId($documentID)],
                    [
                        '$set' => [
                            "documentation" => $softwareUserDoc,
                            "updated_at" => $timestamp
                        ]
                    ]
                );
            }

            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {
                $option = [
                    "project_id"                => $checkEditData[0]->project_id,
                    "creater_by"                => $checkEditData[0]->creater_by,
                    // "repository_name"           => $repository_name,
                    // "description"               => $description,
                    // "tester_id"                 => $this->MongoDBObjectId($documentID),
                    // "topics"                    => $dataList,
                    "documentation"             => $softwareUserDoc,
                    "version"                   => $checkEditData[0]->version . "_edit",
                    "is_edit"                   => true,
                    "status"                    => null,
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("SoftwareDesign")->updateOne(
                    ['_id' => $this->MongoDBObjectId($documentID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );

                $insertForEditApproved = $this->db->selectCollection("SoftwareDesign")->insertOne($option);
            }
            // $update = $this->db->selectCollection("SoftwareDesign")->updateOne(
            //     ["_id" => $this->MongoDBObjectId($documentID)],
            //     ['$set' => [
            //         "documentation" => $softwareUserDoc,
            //         "updated_at" => $timestamp
            //     ]]
            // );

            return response()->json([
                "status" => "success",
                "message" => "Updated successfully",
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

    //! [GET]/software-design/All-List
    public function GetAllSoftwareDesign(Request $request)
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
                ['$sort' => ['created_at' => 1]],
                ['$group' => [
                    '_id' => '$project_id',
                    'latest_version' => ['$last' => '$$ROOT'],
                ]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'latest_version.creater_by', 'foreignField' => '_id', 'as' => 'Users']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Users', 0]], '$latest_version']]]],
                ['$lookup' => [
                    'from' => 'Projects',
                    'localField' => 'project_id',
                    'foreignField' => '_id',
                    'as' => 'projectInfo'
                ]],
                ['$unwind' => '$projectInfo'],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "document_name" => 1,
                    "documentation" => 1,
                    "version" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "updated_at" => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "is_edit" => 1,
                    "creater_by" => ['$toString' => '$creater_by'],
                    "name" => 1,
                    "project_name" => '$projectInfo.project_name',
                    "customer_name" => '$projectInfo.customer_name',
                    "project_type" => '$projectInfo.project_type',
                ]],
            ];

            $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipeline);

            $dataUserDoc = array();
            foreach ($userDoc as $doc) {
                $status = null;


                $dataShow = [
                    "project_id" => $doc->project_id,
                    "project_name" => $doc->project_name,
                    "customer_name" => $doc->customer_name,
                    "project_type" => $doc->project_type,
                    "document_id" => $doc->_id,
                    "document_name" => $doc->document_name,
                    "documentation" => $doc->documentation,
                    "version" => $doc->version,
                    "created_at" => $doc->created_at,

                    "updated_at" => $doc->updated_at,
                    "is_edit" => $doc->is_edit,
                    "status" => $status, // "approved", "rejected", "pending
                    "created_by" => $doc->creater_by,
                    "creator_name" => $doc->name,
                ];
                array_push($dataUserDoc, $dataShow);
            }
            return response()->json([
                "status" => "success",
                "message" => "Document found",
                "data" => $dataUserDoc
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [GET] /software-design/get-doc // Get ducuments for each project_id
    public function GetSoWDoc(Request $request)
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
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creater_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "project_name" => 1,
                    // "version" => 1,
                    // "is_edit" => 1,
                    // "status" => 1,
                    "creator_id" => ['$toString' => '$creator_id'],
                    "name_en" => 1,
                    "customer_name" => 1,
                    "project_type" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "updated_at" => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                [
                    '$group' => [
                        '_id' => ['project_id' => '$project_id', 'project_name' => '$project_name', 'project_type' => '$project_type', 'name_en' => '$name_en', 'customer_name' => '$customer_name'],
                        'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], "document_id" => ['$last' => '$_id']
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => '$_id.project_id',
                    "project_name" => '$_id.project_name',
                    // "statement_of_work_id" => '$document_id',
                    // "status" => 1,
                    "customer_name" => '$_id.customer_name',
                    "project_type" => '$_id.project_type',
                    // "version" => 1,
                    "created_at" => 1,
                    "updated_at" => 1,
                    // "is_edit" => 1,
                    // "creator_id" => '$_id.creator_id',
                    "creator_by" => '$_id.name_en'
                ]],
            ];

            $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
            $dataUserDoc = array();
            // foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);
            foreach ($userDoc as $doc) {
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($doc->project_id)]],
                    // ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    // ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            'version' => 1, 'software_design_id' => ['$toString' => '$_id'], 'status' => 1, 'is_edit' => 1
                        ]
                    ]
                ];
                $allVersion = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
                $versionsAll = array();
                foreach ($allVersion as $ver) {
                    $version = $ver->version;
                    $softwaeDesignID = $ver->software_design_id;
                    // $projectName = isset($ver->project_name) ? $ver->project_name : null;
                    $status = isset($ver->status) ? $ver->status : null;
                    $isEdit = $ver->is_edit;
                    // $startDate = isset($ver->start_date) ? $ver->start_date : null;
                    // $endDate = isset($ver->endDate) ? $ver->endDate : null;
                    // $projectType = isset($ver->projectType) ? $ver->projectType : null;
                    array_push($versionsAll, [
                        "version" => $version, "software_design_id" => $softwaeDesignID, "status" => $status, "is_edit" => $isEdit
                    ]);
                }
                $versions = ["version_all" => $versionsAll];

                $data = array_merge((array)$doc, $versions);
                array_push($dataUserDoc, $data);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get all Software Design successfully !!',
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

    //* [POST] /software-design/get-individual-doc
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
                'software_design_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $softwareDesignID = $request->software_design_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($softwareDesignID)]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creater_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "software_design_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "creator_id" => ['$toString' => '$creater_by'],
                    "creator_name" => '$name_en',
                    "project_type" => 1,
                    "is_edit" => 1,
                    "documentation" => 1,
                    "project_name" => 1,
                    "customer_name" => 1,
                    "version" => 1,
                    // "customer_contact" => 1,
                    // "cost_estimation" => 1,
                    // "sap_code" => 1,
                    // "introduction_of_project" => 1,
                    // "list_of_introduction" => 1,
                    // "scope_of_project" => 1,
                    // "objective_of_project" => 1,
                    "start_date" => 1,
                    "end_date" => 1,
                    "create_date" => 1,
                ]]
            ];
            $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipline);
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
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creater_by', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
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
                    "version" => 1,
                    "conductor" => 1,
                    "reviewer" => 1,
                    "approver" => '$name_en',
                    "created_at" => 1,
                    "verified_at" => 1,
                    "validated_at" => 1,
                    "approver_number" => 1,
                    "verification_type" => 1,
                ]],
            ];
            $userCov = $this->db->selectCollection("SoftwareDesign")->aggregate($cover);
            $dataCover = array();
            // foreach ($userCov as $cov) \array_push($dataCover, $cov);
            // return response()->json($dataCover);

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
            };
            return response()->json([
                'status' => 'success',
                'message' => 'Get Statement of Work details successfully !!',
                "data" => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $dataUserDoc,

                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
