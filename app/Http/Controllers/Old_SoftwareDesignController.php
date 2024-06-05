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
                "document_id" => "required | string | min:1 | max:255"
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $documentID = $request->document_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($documentID)]],
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
                "document_id" => "required | string | min:1 | max:255",
                "documentation" => "required | string | min:1"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $documentID = $request->document_id;
            $copyData = $this->db->selectCollection("SoftwareDesign")->find(["_id" => $this->MongoDBObjectId($documentID)]);
            $dataCopy = iterator_to_array($copyData);

            if (empty($dataCopy)) {
                return response()->json([
                    "status" => "error",
                    "message" => "Document ID not found",
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

            $update = $this->db->selectCollection("SoftwareDesign")->updateOne(
                ["_id" => $this->MongoDBObjectId($documentID)],
                ['$set' => [
                    "documentation" => $softwareUserDoc,
                    "updated_at" => $timestamp
                ]]
            );

            return response()->json([
                "status" => "success",
                "message" => "Updated successfully",
                "data" => [$update->getModifiedCount()]
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

            $userDoc = $this->db->selectCollection("SoftwareDesign")->aggregate($pipeline);

            $dataUserDoc = array();
            foreach ($userDoc as $doc) {
                $status = null;


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
}
