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

class ProjectPlaningController extends Controller
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

    //* [POST] /project-plan/add-planing
    public function newProjectPlaning(Request $request)
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
                'statement_of_work_id'         => 'required | string ',
                'project_id'                   => 'required | string ',
                // 'teamspace_id'                 => 'required | string ',
                'job_order'                    => 'nullable | string ',
                'project_repo'                 => 'required | string ',
                'project_backup'               => 'required | string ',
                'selling_prices'               => 'nullable | string ',
                'software_requirement'         => ['required', 'array'],
                "responsibility"               => ['required', 'array'],
                "equipments"                   => ['required', 'array'],
                // 'system_requirement'           => ['nullable', 'array'],
                // 'requirement_code'             => 'nullable | array',
                // 'requiremet_details'           => 'nullable | array',
                // "software_desc"                => ['nullable', 'array'],
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

            // $timestamp = $this->MongoDBUTCDatetime(time() * 1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            //! check data
            $filter1 = ["_id" => $this->MongoDBObjectId($request->project_id)];
            $options1 = ["projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id'], "project_name" => 1, "project_type" => 1]];

            $chkProject = $this->db->selectCollection("Projects")->find($filter1, $options1);
            $dataChk1 = array();
            foreach ($chkProject as $doc) \array_push($dataChk1, $doc);
            if (\count($dataChk1) == 0)
                return response()->json(["status" => "error", "message" => "Project id dosen't exsit", "data" => []], 500);

            $filter2 = ["_id" => $this->MongoDBObjectId($request->statement_of_work_id)];
            $options2 = ["limit" => 1, "projection" => ["_id" => 0, "statement_of_work_id" => ['$toString' => '$_id'], "project_id" => ['$toString' => '$project_id'], "teamspace_id" => ['$toString' => '$teamspace_id'],]];
            $chkStatement = $this->db->selectCollection("StatementOfWork")->find($filter2, $options2);

            $dataChk2 = array();
            foreach ($chkStatement as $doc) \array_push($dataChk2, $doc);
            if (\count($dataChk2) == 0)
                return response()->json(["status" => "error", "message" => "Statement of work dosen't exsit", "data" => []], 500);
            //! check data

            $statementOfWorkID  = $request->statement_of_work_id;

            $projectID          = $request->project_id;

            //! Need to pass statement of work
            // $pipline = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$sort'=>['created_at' => 1]],
            //     ['$group' => ['_id' => ['project_id'=>'$project_id'], 'status'=> ['$last' => '$status']]],
            //     ['$project' => [
            //         "_id" => 0,
            //         "status" => 1,
            //     ]]
            // ];
            // $checkStatus = $this->db->selectCollection("StatementOfWork")->aggregate($pipline);
            // $dataCheckStatus = array();
            // foreach ($checkStatus as $doc) \array_push($dataCheckStatus, $doc);

            // if($dataCheckStatus[0]->status !== true){
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Project planing is not verified',
            //         "data" => [],
            //     ], 400);
            // }


            $projectName = $this->db->selectCollection("Projects")->findOne($filter1, $options1)->project_name;

            $jobOrder               = $request->job_order;
            $sellingPrices          = $request->selling_prices;
            // $systemRequirement      = $request->system_requirement;
            $softwareRequirement    = $request->software_requirement;

            $responsibility         = $request->responsibility;

            $equipments             = $request->equipments;
            $createrID              = $decoded->creater_by;
            $projectRepo            = $request->project_repo;
            $projectBackup          = $request->project_backup;


            // if documrnt has been created, cannot create again
            $checkDoc = $this->db->selectCollection("ProjectsPlaning")->findOne(['project_id' => $this->MongoDBObjectId($projectID)]);
            if ($checkDoc !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This document has been created',
                    "data" => [],
                ], 400);
            }

            $dataSoftwareReq = [];
            foreach ($softwareRequirement as $doc) \array_push($dataSoftwareReq, $doc);

            $dataResponsibility = [];
            foreach ($responsibility as $info) \array_push($dataResponsibility, $info);

            $dataListResponsibility = [];
            for ($i = 0; $i < count($responsibility); $i++) {
                $list = [
                    "account_id"      => $this->MongoDBObjectId($dataResponsibility[$i]["account_id"]),
                    "role_id"  => $this->MongoDBObjectId($dataResponsibility[$i]["role_id"]),
                ];
                array_push($dataListResponsibility, $list);
            };

            // return response()->json($dataListResponsibility);

            $dataList = [];
            for ($i = 0; $i < count($dataSoftwareReq); $i++) {

                $list = ["req_code" => "REQ" . "_" . \str_pad($i + 1, 3, "0", STR_PAD_LEFT), "req_details" => $dataSoftwareReq[$i]];
                array_push($dataList, $list);
            }


            $document = [
                "statement_of_work_id"      => $this->MongoDBObjectId($statementOfWorkID),
                "project_id"                => $this->MongoDBObjectId($projectID),
                "creator_id"                => $this->MongoDBObjectId($createrID),
                "job_order"                 => $jobOrder,
                "selling_prices"            => $sellingPrices,
                "project_repo"              => $projectRepo,
                "project_backup"            => $projectBackup,
                // "system_requirement"        => $systemRequirement,
                "software_requirement"      => $dataList,
                "responsibility"            => $dataListResponsibility,
                "equipments"                => $equipments,
                "version"                   => "0.01",
                "is_edit"                   => null,
                "status"                    => null,
                // "is_verified"               => false,
                // "verified_id"               => null,
                // "is_validated"              => false,
                // "validated_id"              => null,
                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,
            ];

            $result = $this->db->selectCollection('ProjectsPlaning')->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 400);

            return response()->json([
                "status" => "success",
                "message" => "ํAdd project planing successfully !!",
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

    //* [PUT] /project-plan/edit-planing
    public function editProjectPlaning(Request $request)
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
                'project_plan_id'              => 'required | string ',
                'job_order'                    => 'nullable | string ',
                'selling_prices'               => 'nullable | string ',
                'project_repo'                 => 'required | string ',
                'project_backup'               => 'required | string ',
                'software_requirement'         => ['required', 'array'],
                "responsibility"               => ['required', 'array'],
                "equipments"                   => ['required', 'array'],
                // 'statement_of_work_id'         => 'required | string ',
                // 'project_id'                   => 'required | string ',
                // 'system_requirement'           => ['nullable', 'array'],
                // 'requirement_code'             => 'nullable | array',
                // 'requiremet_details'           => 'nullable | array',
                // "software_desc"                => ['nullable', 'array'],

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


            $projectPlanID          = $request->project_plan_id;
            $jobOrder               = $request->job_order;
            $sellingPrices          = $request->selling_prices;
            $projectRepo            = $request->project_repo;
            $projectBackup          = $request->project_backup;
            $softwareRequirement    = $request->software_requirement;
            $responsibility         = $request->responsibility;
            $equipments             = $request->equipments;
            // $statementOfWorkID      = $request->statement_of_work_id;
            // $projectID              = $request->project_id;
            // $systemRequirement      = $request->system_requirement;
            // $software_desc          = $request->software_desc;
            // $requirement_code       = $request->requirement_code;
            // $requiremet_details     = $request->requiremet_details;


            //! check data
            $pipeline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($projectPlanID)]],
                ['$project' =>
                [
                    '_id' => 0,
                    'project_plan_id' => '$_id',
                    'statement_of_work_id' => ['$toString' => '$statement_of_work_id'],
                    'project_id' => ['$toString' => '$project_id'],
                    'creator_id' => ['$toString' => '$creator_id'],
                    'job_order' => 1, 'project_repo' => 1, 'project_backup' => 1, 'software_requirement' => 1, 'selling_prices' => 1,
                    'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => [
                        'account_id' => ['$toString' => '$$resp.account_id'],
                        'role_id' => ['$toString' => '$$resp.role_id']
                    ]]],
                    'equipments' => 1, 'version' => 1,
                    'is_edit' => 1, 'status' => 1, 'created_at' => 1, 'updated_at' => 1
                ]]
            ];
            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);
            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);


            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => [],
                ], 500);

            // // If is_edit is fasle, cannot edit
            // if ($data[0]->is_edit === false) {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'Cannot edit this document',
            //         "data" => [],
            //     ], 400);
            // }



            $dataSoftwareReq = [];
            foreach ($softwareRequirement as $doc) \array_push($dataSoftwareReq, $doc);

            $dataList = [];
            for ($i = 0; $i < count($dataSoftwareReq); $i++) {

                $list = ["req_code" => "REQ" . "_" . \str_pad($i + 1, 3, "0", STR_PAD_LEFT), "req_details" => $dataSoftwareReq[$i]];
                array_push($dataList, $list);
            }

            //? Check responsibility change it to object id
            $responsOBJ = [];
            for ($i = 0; $i < count($responsibility); $i++) {
                // return response()->json($responsibility[$i] );
                if ($responsibility[$i]) {

                    $accountId = $this->MongoDBObjectId($responsibility[$i]['account_id']);
                    $roleId = $this->MongoDBObjectId($responsibility[$i]['role_id']);
                    $responsOBJ[] = [
                        'account_id' => $accountId,
                        'role_id' => $roleId
                    ];
                }
            }
            // return response()->json($data);



            if ($data[0]->is_edit !== false && $data[0]->status === null) {

                $updateDoc = $this->db->selectCollection("ProjectsPlaning")->updateOne(
                    ['_id' => $this->MongoDBObjectId($projectPlanID)],
                    ['$set' => [
                        // "statement_of_work_id"      => $this->MongoDBObjectId($statementOfWorkID),
                        // "project_id"                => $this->MongoDBObjectId($projectID),
                        // "creator_id"                => $this->MongoDBObjectId($decoded->creater_by),
                        "job_order"                 => $jobOrder,
                        "selling_prices"            => $sellingPrices,
                        "project_repo"              => $projectRepo,
                        "project_backup"            => $projectBackup,
                        "software_requirement"      => $dataList,
                        "responsibility"            => $responsOBJ,
                        "equipments"                => $equipments,
                        // "software_desc"             => $software_desc,
                        // "requirement_code"          => $requirement_code,
                        // "requiremet_details"        => $requiremet_details,
                        // "version"                   => $this->MongoDBObjectId($data[0]->version),
                        // "is_edit"                   => true,
                        // "status"                    => null,
                        "updated_at"                => $timestamp,
                    ]]
                );
                return response()->json([
                    'status' => 'success',
                    'message' => 'Project planing has been updated successfully1',
                    "data" => [
                        "statement_of_work_id" => $data[0]->statement_of_work_id, "project_id" => $data[0]->project_id,
                        "creator_id" => $data[0]->creator_id, "job_order" => $jobOrder, "selling_prices" => $sellingPrices,
                        "project_repo" => $projectRepo, "project_backup" => $projectBackup, "software_requirement" => $dataList,
                        "responsibility" => $responsibility, "equipments" => $equipments,  "updated_at" => $date,
                        "version" => $data[0]->version . "_edit",
                    ],
                ], 200);
            }

            if ($data[0]->is_edit !== false && $data[0]->status !== null) {
                // return response()->json('gg');
                $statementOfWorkId = $this->MongoDBObjectId($data[0]->statement_of_work_id);
                $projectId = $this->MongoDBObjectId($data[0]->project_id);
                $creatorId = $this->MongoDBObjectId($data[0]->creator_id);

                $option = [
                    "statement_of_work_id"      => $statementOfWorkId,
                    "project_id"                => $projectId,
                    "creator_id"                => $creatorId,
                    "job_order"                 => $jobOrder,
                    "selling_prices"            => $sellingPrices,
                    "project_repo"              => $projectRepo,
                    "project_backup"            => $projectBackup,
                    "software_requirement"      => $dataList,
                    "responsibility"            => $responsOBJ,
                    "equipments"                => $equipments,
                    // "software_desc"             => $software_desc,
                    // "requirement_code"          => $requirement_code,
                    // "requiremet_details"        => $requiremet_details,
                    "version"                   => $data[0]->version . "_edit",
                    "is_edit"                   => true,
                    "status"                    => null,
                    "updated_at"                => $timestamp,
                    "created_at"                => $timestamp,
                ];
                $seteditfalse = $this->db->selectCollection("ProjectsPlaning")->updateOne(
                    ['_id' => $this->MongoDBObjectId($projectPlanID)],
                    ['$set' => [
                        "is_edit"                   => false,
                    ]]
                );
                // return response()->json('gg');
                $inserteditApproved = $this->db->selectCollection("ProjectsPlaning")->insertOne($option);
                return response()->json([
                    'status' => 'success',
                    'message' => 'Project planing has been updated successfully2',
                    "data" => [
                        "statement_of_work_id" => $data[0]->statement_of_work_id,
                        "project_id"        => $data[0]->project_id,
                        "creator_id"        => $data[0]->creator_id,
                        "job_order"         => $jobOrder,
                        "selling_prices"    => $sellingPrices,
                        "project_repo"      => $projectRepo,
                        "project_backup"    => $projectBackup,
                        "software_requirement" => $dataList,
                        "responsibility"        => $responsibility,
                        "equipments"        => $equipments,
                        // "software_desc" => $software_desc,
                        // "requirement_code" => $requirement_code,
                        // "requiremet_details" => $requiremet_details,
                        "version"           => $data[0]->version . "_edit",
                        "is_edit"           => true, "status" => null,
                        "updated_at"        => $date, "created_at" => $date
                    ],
                ], 200);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data" => [],
            ], $statusCode);
        }
    }

    //* [DELETE] /project-plan/delete-planing
    public function deleteProjectPlaning(Request $request)
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
                'project_plan_id'  => 'required | string | min:1 | max:255',
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

            $projectPlanID          = $request->project_plan_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($projectPlanID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "statement_of_work_id" => ['$toString' => '$statement_of_work_id'], "project_id" => ['$toString' => '$project_id'],]];
            $result = $this->db->selectCollection("ProjectsPlaning")->find($filter, $options);
            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "Project plan dose't exit",
                    "data" => [],
                ], 400);
            //! check data

            $result = $this->db->selectCollection("ProjectsPlaning")->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "delete project planing failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "delete project planing successfully",
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

    //* [GET] /project-plan/get-planing
    public function getProjectPlaning(Request $request)
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
                ['$project' => [
                    '_id' => 0, 'project_plan_id' => ['$toString' => '$_id'], 'statement_of_work_id' => ['$toString' => '$statement_of_work_id'], 'creator_id' => ['$toString' => '$creator_id'],
                    'project_id' => ['$toString' => '$project_id'], 'job_order' => 1, "selling_prices" => 1, 'project_repo' => 1, 'project_backup' => 1, 'system_requirement' => 1,
                    'software_requirement' => 1, 'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => [
                        'account_id' => ['$toString' => '$$resp.account_id'],
                        'role_id' => ['$toString' => '$$resp.role_id']
                    ]]], 'equipments' => 1, 'version' => 1, 'is_edit' => 1, 'status' => 1
                ]]
            ];

            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status"    => "success",
                "message"   => "Get project planing successfully",
                "data"      => $data,
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] /project-plan/get-planing-req
    public function requirementPlaning(Request $request)
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
                'project_id'           => 'required | string | min:1 | max:255',
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

            $filter = ["project_id" => $this->MongoDBObjectId($request->project_id)];

            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->find($filter);

            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project ID not found", "data" => []], 400);

            $pipeline = [['$sort' => ['created_at' => -1]], ['$limit' => 1], ['$project' => ['_id' => 0, 'project_id' => 1, 'software_requirement' => 1]]];

            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "you get software requirement of planing successfully",
                "data" =>
                $data[0]->software_requirement,
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] /project-plan/get-planing-detail
    public function GetProjectPlaningDetails(Request $request)
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
                'project_plan_id'       => 'required | string | min:1 | max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $projectPlanId = $request->project_plan_id;


            $pipeline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($projectPlanId)]],

                ['$lookup' => [
                    'from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result_sapcode',
                    'pipeline' => [['$project' => ['_id' => 0, 'project_type_id' => 1]]]
                ]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_sapcode', 0]], '$$ROOT']]]],
                ['$lookup' => [
                    'from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'result_types',
                    'pipeline' => [['$project' => ['_id' => 0, 'description' => 1]]]
                ]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_types', 0]], '$$ROOT']]]],

                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creator_user', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creator_user', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'verified_by_user', 'pipeline' => [['$project' => ['_id' => 0, 'verified_user' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$verified_by_user', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'project_plan_id' => ['$toString' => '$_id'], 'sap_code' => '$description', 'project_id' => ['$toString' => '$project_id'], 'project_name' => 1, 'project_type' => 1, 'is_edit' => 1, 'status' => 1, 'customer_name' => 1, 'job_order' => 1, "selling_prices" => 1, 'project_repo' => 1, 'project_backup' => 1, 'system_requirement' => 1, 'software_requirement' => 1, 'responsibility' => 1, 'equipments' => 1, 'version' => 1, 'responsibility' => 1, 'statement_of_work_id' => ['$toString' => '$statement_of_work_id'], 'creator_name' => '$creator_name', 'verified_by' => '$verified_user', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$unwind' => '$responsibility'],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id', 'picture' => 1, 'user_id' => 1, 'email' => '$username']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creatorName', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en', 'picture' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creatorName', 0]], '$$ROOT']]]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'version' => ['$last' => '$version'], 'job_order' => ['$last' => '$job_order'], "selling_prices" => ['$last' => '$selling_prices'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'software_requirement' => ['$last' => '$software_requirement'], 'equipments' => ['$last' => '$equipments'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'equipments' => ['$last' => '$equipments'],
                    'project_id' => ['$last' => '$project_id'], 'equipments' => ['$last' => '$equipments'], 'creator_name' => ['$last' => '$creator_name'], 'verified_by' => ['$last' => '$verified_by'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'is_edit' => ['$last' => '$is_edit'],
                    'status' => ['$last' => '$status'], 'sap_code' => ['$last' => '$sap_code'],
                    'creator_name' => ['$last' => '$creator_name'], 'verified_by' => ['$last' => '$verified_by'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'responsibility' => ['$push' => ['account_id' => ['$toString' => '$responsibility.account_id'], 'role_id' => ['$toString' => '$responsibility.role_id'], 'email' => '$email', 'picture' => '$picture', 'name_en' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'sap_code' => 1, 'project_plan_id' => 1, 'statement_of_work_id' => 1, 'job_order' => 1, "selling_prices" => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'is_edit' => 1, 'status' => 1, 'project_repo' => 1, 'project_backup' => 1, 'software_requirement' => 1, 'equipments' => 1, 'responsibility' => 1, 'creator_name' => 1, 'verified_by' => 1, 'created_at' => 1, 'updated_at' => 1, 'version' => 1]]
            ];



            $userDoc = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);

            // return response()->json($dataUserDoc[0]);

            if (\count($dataUserDoc) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 400);

            $projectID = $dataUserDoc[0]->project_id;

            // return response()->json($dataUserDoc[0]->version);

            // $cover = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
            //     ['$project' => [
            //         "project_id" => ['$toString' => '$project_id'],
            //         "version" => 1,
            //         "conductor" => '$name_en',
            //         "reviewer" => ['$arrayElemAt' => ['$customer_contact.name', 0]],
            //         "verified_by" => 1,
            //         "verification_type" => 1,
            //         "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         "verified_at" => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         "validated_at" => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //     ]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationType', 0]], '$$ROOT']]]],
            //     ['$project' => [
            //         "_id" => 0,
            //         "project_id" => 1,
            //         "version" => 1,
            //         "conductor" => 1,
            //         "reviewer" => 1,
            //         "approver" => '$name_en',
            //         "created_at" => 1,
            //         "verified_at" => 1,
            //         "validated_at" => 1,
            //         "approver_number" => 1,
            //         "verification_type" => 1,
            //     ]],
            // ];
            $cover = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' => $dataUserDoc[0]->version]]],
                ['$lookup' => [
                    'from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'result_sapcode',
                    'pipeline' => [['$project' => ['_id' => 0, 'project_type_id' => 1]]]
                ]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_sapcode', 0]], '$$ROOT']]]],
                ['$lookup' => [
                    'from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'result_types',
                    'pipeline' => [['$project' => ['_id' => 0, 'description' => 1]]]
                ]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_types', 0]], '$$ROOT']]]],

                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1, 'name_en' => 1, 'username' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'Approved']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approved', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork', 'pipeline' => [['$project' => ['_id' => 0, 'customer_contact' => 1, 'project_name' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'conductor' => '$name_en', 'reviewer' => ['$arrayElemAt' => ['$customer_contact.name', 0]], 'verified_by' => 1, 'verification_type' => 1,
                    'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'verified_at' => ['$dateToString' => ['date' => '$verified_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'validated_at' => ['$dateToString' => ['date' => '$validated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'sap_code' => '$description',

                ]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'verified_by', 'foreignField' => 'user_id', 'as' => 'Approve', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1, 'name_en' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Approve', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'VerificationType', 'localField' => 'verification_type', 'foreignField' => 'verification_type', 'as' => 'VerificationType', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'approver_number' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$VerificationType', 0]], '$$ROOT']]]],
                ['$project' => [
                    '_id' => 0,
                    'project_id' => 1,
                    'version' => 1,
                    'conductor' => 1,
                    'reviewer' => 1,
                    'approver' => '$name_en',
                    'created_at' => 1,
                    'verified_at' => 1,
                    'validated_at' => 1,
                    'verification_type' => 1,
                    'approver_number' => 1,
                    'sap_code' => 1,
                    ''
                ]]
            ];

            $userCov = $this->db->selectCollection("ProjectsPlaning")->aggregate($cover);
            // $userCov = $this->db->selectCollection("ProjectsPlaning")->find(['project_id' => $this->MongoDBObjectId($projectID)]);

            $dataCover = array();

            // foreach ($userCov as $cov) \array_push($dataCover, $cov);
            // return response()->json($dataCover);

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
                        "project_id" => $cov->project_id,
                        "sap_code" => $cov->sap_code,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "reviewer" => ['reviewer' => null, 'validated_at' => null],
                        "description" => "Created",
                        "created_at" => $cov->created_at,
                    ];
                    array_push($dataCover, $coverData);
                } else {
                    $coverData = [
                        "project_id" => $cov->project_id,
                        "sap_code" => $cov->sap_code,
                        "version" => $cov->version,
                        "conductor" => ["conductor" => $cov->conductor, "created_at" => $cov->created_at],
                        "approver" => ["approver" => null, "verified_at" => null],
                        "reviewer" => ['reviewer' => null, 'validated_at' => null],
                        "description" => "Edited",
                        "created_at" => $cov->created_at,
                    ];
                    array_push($dataCover, $coverData);
                }
            };

            return response()->json([
                'status' => 'success',
                'message' => 'Get Planing details successfully !!',
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

    //* [GET]  /project-plan/project-planing-show-latest-version  get project planing show latest version and show all version in same projectID
    public function ProjectPlaningShowLatestVersion(Request $request)
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
            //     ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result']],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creatorName', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creatorName', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'version_all', 'pipeline' => [
            //         ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'statement_of_work_id', 'foreignField' => '_id', 'as' => 'result2', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'customer_name' => 1]]]]],
            //         ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result2', 0]], '$$ROOT']]]],
            //         ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'creator_name' => '$name_en', 'user_id' => 1, 'position_id' => 1, 'team_id' => 1, 'picture' => 1, 'name_en' => 1, 'email' => '$username', 'official_email' => 1]]]]],
            //         ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role']],
            //         ['$lookup' => ['from' => 'Positions', 'localField' => 'result_acc.position_id', 'foreignField' => '_id', 'as' => 'result_position']],
            //         ['$project' => [
            //             '_id' => 0, 'project_plan_id' => ['$toString' => '$_id'], 'version' => '$version', 'statement_of_work_id' => ['$toString' => '$statement_of_work_id'], 'status' => '$status', 'is_edit' => '$is_edit', 'start_date' => '$start_date', 'end_date' => '$end_date', 'customer_name' => '$customer_name', 'creator_name' => '$creator_name',
            //             'official_email' => '$official_email', 'selling_prices' => '$selling_prices', 'software_requirement' => '$software_requirement', 'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => ['$mergeObjects' => ['$$resp', ['$arrayElemAt' => ['$result_acc', ['$indexOfArray' => ['$responsibility.account_id', '$$resp.account_id']]]], ['$arrayElemAt' => ['$result_role', ['$indexOfArray' => ['$responsibility.role_id', '$$resp.role_id']]]], ['$arrayElemAt' => ['$result_position', ['$indexOfArray' => ['$result_acc.position_id', '$$resp.position_id']]]]]]]]
            //         ]]
            //     ]]],

            //     ['$project' => [
            //         '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'job_order' => 1, 'is_closed' => 1, 'project_type' => 1, 'customer_name' => 1, 'project_name' => 1, 'creator_name' => 1, 'created_at' => 1, 'updated_at' => 1,
            //         'version_all' => ['$map' => ['input' => '$version_all', 'as' => 'version', 'in' => ['project_plan_id' => ['$toString' => '$$version.project_plan_id'], 'version' => '$$version.version', 'statement_of_work_id' => ['$toString' => '$$version.statement_of_work_id'], 'status' => '$$version.status', 'is_edit' => '$$version.is_edit', 'start_date' => '$$version.start_date', 'end_date' => '$$version.end_date', 'responsibility' => ['$map' => ['input' => '$$version.responsibility', 'as' => 'resp', 'in' => ['account_id' => ['$toString' => '$$resp.account_id'], 'name_en' => '$$resp.name_en', 'picture' => '$$resp.picture', 'role_name' => '$$resp.name', 'role_id' => ['$toString' => '$$resp.role_id'], 'position_name' => '$$resp.Position', 'email' => '$$resp.official_email']]]]]]
            //     ]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'version_all' => ['$last' => '$version_all'], 'customer_name' => ['$last' => '$customer_name'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'creator_name' => ['$last' => '$creator_name'],
            //         'project_type' => ['$last' => '$project_type']
            //     ]],
            //     ['$project' => ['_id' => 0, 'customer_name' => 1, 'project_id' => ['$toObjectId' => '$_id'], 'project_name' => 1, 'version_all' => 1, 'is_closed' => 1, 'job_order' => 1, 'creator_name' => 1, 'project_type' => 1]],
            //     ['$lookup' => ['from' => 'Approved', 'localField' => '_id', 'foreignField' => 'document_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'is_validated' => 1, 'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'result_Approved' => 1, 'version_all' => 1, 'project_type' => 1]],
            //     ['$project' => ['_id' => 0, 'created_at' => 1, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'version_all' => 1, 'project_type' => 1]],
            //     ['$project' => ['_id' => 0, 'version_all' => 1, 'created_at' => 1, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'project_type' => 1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'customer_name' => ['$last' => '$customer_name'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'creator_name' => ['$last' => '$creator_name'],
            //         'version_all' => ['$last' => '$version_all'], 'project_type' => ['$last' => '$project_type']
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$_id'], 'project_name' => 1, 'customer_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'creator_name' => 1, 'verification_type' => 1, 'is_validated' => 1, 'version_all' => 1, 'project_type' => 1]]
            // ];

            $pipeline = [
                ['$lookup' => ['from' => 'Projects', 'localField' => 'project_id', 'foreignField' => '_id', 'as' => 'Projects']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Projects', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creatorName', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creatorName', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'version_all', 'pipeline' => [
                    ['$lookup' => [
                        'from' => 'StatementOfWork', 'localField' => 'statement_of_work_id', 'foreignField' => '_id', 'as' => 'result2',
                        'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'customer_name' => 1]]]
                    ]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result2', 0]], '$$ROOT']]]],
                    ['$unwind' => '$responsibility'],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id', 'picture' => 1, 'user_id' => 1, 'email' => '$username']]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result']],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
                    ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'creatorName', 'pipeline' => [['$project' => ['_id' => 1, 'creator_name' => '$name_en', 'picture' => 1]]]]],
                    ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$creatorName', 0]], '$$ROOT']]]],
                    ['$group' => [
                        '_id' => '$_id', 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'version' => ['$last' => '$version'], 'job_order' => ['$last' => '$job_order'], 'project_repo' => ['$last' => '$project_repo'],
                        'project_backup' => ['$last' => '$project_backup'], 'software_requirement' => ['$last' => '$software_requirement'], 'equipments' => ['$last' => '$equipments'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'equipments' => ['$last' => '$equipments'], 'project_id' => ['$last' => '$project_id'],
                        'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date'], 'creator_name' => ['$last' => '$creator_name'], 'verified_by' => ['$last' => '$verified_by'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'is_edit' => ['$last' => '$is_edit'], 'status' => ['$last' => '$status'], 'creator_name' => ['$last' => '$creator_name'],
                        'verified_by' => ['$last' => '$verified_by'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'responsibility' => ['$push' => ['account_id' => ['$toString' => '$responsibility.account_id'], 'role_id' => ['$toString' => '$responsibility.role_id'], 'email' => '$email', 'picture' => '$picture', 'name_en' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
                    ]],
                    ['$lookup' => ['from' => 'Positions', 'localField' => 'result_acc.position_id', 'foreignField' => '_id', 'as' => 'result_position']],
                    ['$project' => [
                        '_id' => 0, 'project_plan_id' => ['$toString' => '$_id'], 'version' => '$version', 'statement_of_work_id' => ['$toString' => '$statement_of_work_id'], 'status' => '$status', 'is_edit' => '$is_edit', 'start_date' => '$start_date', 'end_date' => '$end_date', 'customer_name' => '$customer_name',
                        'creator_name' => '$creator_name', 'official_email' => '$official_email', 'selling_prices' => '$selling_prices', 'software_requirement' => '$software_requirement', 'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => ['$mergeObjects' => [
                            '$$resp', ['$arrayElemAt' => ['$result_acc', ['$indexOfArray' => ['$responsibility.account_id', '$$resp.account_id']]]],
                            ['$arrayElemAt' => ['$result_role', ['$indexOfArray' => ['$responsibility.role_id', '$$resp.role_id']]]],
                            ['$arrayElemAt' => ['$result_position', ['$indexOfArray' => ['$result_acc.position_id', '$$resp.position_id']]]]
                        ]]]]
                    ]]
                ]]],
                ['$project' => [
                    '_id' => 1, 'project_id' => ['$toString' => '$project_id'], 'job_order' => 1, 'is_closed' => 1, 'project_type' => 1, 'customer_name' => 1, 'project_name' => 1, 'creator_name' => 1, 'created_at' => 1, 'updated_at' => 1,
                    'version_all' => ['$map' => ['input' => '$version_all', 'as' => 'version', 'in' => ['project_plan_id' => ['$toString' => '$$version.project_plan_id'], 'version' => '$$version.version', 'statement_of_work_id' => ['$toString' => '$$version.statement_of_work_id'], 'status' => '$$version.status', 'is_edit' => '$$version.is_edit', 'start_date' => '$$version.start_date', 'end_date' => '$$version.end_date', 'responsibility' => ['$map' => ['input' => '$$version.responsibility', 'as' => 'resp', 'in' => ['account_id' => ['$toString' => '$$resp.account_id'], 'name_en' => '$$resp.name_en', 'picture' => '$$resp.picture', 'role_name' => '$$resp.role_name', 'role_id' => ['$toString' => '$$resp.role_id'], 'position_name' => '$$resp.position_name', 'email' => '$$resp.email']]]]]]
                ]],
                ['$group' => ['_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'version_all' => ['$last' => '$version_all'], 'customer_name' => ['$last' => '$customer_name'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'creator_name' => ['$last' => '$creator_name'], 'project_type' => ['$last' => '$project_type']]],
                ['$project' => ['_id' => 1, 'customer_name' => 1, 'project_id' => ['$toObjectId' => '$_id'], 'project_name' => 1, 'version_all' => 1, 'is_closed' => 1, 'job_order' => 1, 'creator_name' => 1, 'project_type' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => [
                    '_id' => 0, 'verification_type' => 1, 'is_validated' => 1,
                    'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at'
                ]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'result_Approved' => 1, 'version_all' => 1, 'project_type' => 1]],
                ['$project' => ['_id' => 0, 'created_at' => 1, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'version_all' => 1, 'project_type' => 1]],
                ['$project' => ['_id' => 0, 'version_all' => 1, 'created_at' => 1, 'project_id' => 1, 'project_name' => 1, 'customer_name' => 1, 'creator_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'project_type' => 1]],
                ['$group' => ['_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'customer_name' => ['$last' => '$customer_name'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'creator_name' => ['$last' => '$creator_name'], 'version_all' => ['$last' => '$version_all'], 'project_type' => ['$last' => '$project_type']]],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$_id'], 'project_name' => 1, 'customer_name' => 1, 'is_closed' => 1, 'job_order' => 1, 'creator_name' => 1, 'verification_type' => 1, 'is_validated' => 1, 'version_all' => 1, 'project_type' => 1]]
            ];

            $dataProject = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);
            $dataProDoc = array();

            foreach ($dataProject as $proj) {
                foreach ($proj->version_all as $ver) {
                    $projectPlanID = $ver->project_plan_id;
                    $validated = $this->db->selectCollection("Approved")->find(['document_id' => $this->MongoDBObjectId($projectPlanID)]);

                    foreach ($validated as $val) {
                        $a = $val->is_validated;
                        $proj['is_validated'] = $a;
                    }
                }
                \array_push($dataProDoc, $proj);
            }

            foreach ($dataProDoc as $info) {
                if (!key_exists('is_validated', (array)$info)) {
                    // $dbcIs[] = "yes";
                    $info['is_validated'] = null;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get all Projects Planing Documentation successfully !!',
                "data" => $dataProDoc,
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    //* [GET] /project-plan/get-software-req
    public function getSoftwareRequirement(Request $request)
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

            $rules = [
                'project_id' => 'required|string|min:1|max:255',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors(),
                        ]
                    ]
                ], 400);
            }

            $projectId = $request->input('project_id');

            // Check if project_id exists in the MongoDB collection
            $filter = ['project_id' => $this->MongoDBObjectId($projectId)];
            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->findOne($filter);

            if (!$chkProjectID) {
                return response()->json([
                    "status" => "error",
                    "message" => "Project ID not found",
                    "data" => []
                ], 400);
            }

            // Aggregation pipeline to get the latest software requirement
            $pipeline = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectId)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'software_requirement' => 1]],
            ];

            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);

            $data = iterator_to_array($result);

            return response()->json([
                "status" => "success",
                "message" => "You get the software requirement of planning successfully",
                "data" => isset($data[0]['software_requirement']) ? $data[0]['software_requirement'] : null,
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [GET] /project-plan/last-version
    public function lastPlaningVersion(Request $request)
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

            $pipeline = [
                ['$project' => ['_id' => 1, 'statement_of_work_id' => 1, 'project_id' => 1, 'creator_id' => 1, 'job_order' => 1, "selling_prices" => 1, 'project_repo' => 1, 'project_backup' => 1, 'system_requirement' => 1, 'software_requirement' => 1, 'responsibility' => 1, 'equipments' => 1, 'version' => 1, 'is_edit' => 1, 'created_at' => 1, 'updated_at' => 1]],
                ['$group' => ['_id' => ['$toString' => '$statement_of_work_id'], 'version' => ['$last' => '$version'], 'project_plan_id' => ['$last' => ['$toString' => '$_id']], 'project_id' => ['$last' => ['$toString' => '$project_id']], 'creator_id' => ['$last' => ['$toString' => '$creator_id']], 'job_order' => ['$last' => '$job_order'], "selling_prices" => ['$last' => '$selling_prices'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'system_requirement' => ['$last' => '$system_requirement'], 'responsibility' => ['$last' => '$responsibility'], 'equipments' => ['$last' => '$equipments'], 'is_edit' => ['$last' => '$is_edit'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at']]],
                ['$project' => ['_id' => 0, 'project_plan_id' => ['$toString' => '$project_plan_id'], 'statement_of_work_id' => ['$toString' => '$_id'], 'version' => 1, 'project_id' => 1, 'creator_id' => 1, 'job_order' => 1, "selling_prices" => 1, 'project_repo' => 1, 'project_backup' => 1, 'system_requirement' => 1, 'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => ['account_id' => ['$toString' => '$$resp.account_id'], 'role_id' => ['$toString' => '$$resp.role_id']]]], 'equipments' => 1, 'is_edit' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]
            ];

            $result = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            // $responseMember = $data[0]->responsibility;

            // return response()->json($responseMember[1]);

            return response()->json([
                "status"    => "success",
                "message"   => "Get all Projects Planing last version successfully !!",
                "data"      => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] /project-plan/get-individual-doc
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
                'project_plan_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $PlaningID = $request->project_plan_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($PlaningID)]],
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'ProjectsPlaning']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$ProjectsPlaning', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "traceability_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "creator_id" => ['$toString' => '$creator_id'],
                    "creator_name" => '$name_en',
                    "version" => 1,
                    "document_name" => 1,
                    "documentation" => 1,
                    "job_order" => 1,
                    "selling_prices" => 1,
                    // "project_repo" => 1,
                    // "project_backup" => 1,
                    "system_requirement" => 1,
                    "software_requirement" => 1,
                    'responsibility' => ['$map' => ['input' => '$responsibility', 'as' => 'resp', 'in' => ['account_id' => ['$toString' => '$$resp.account_id'], 'role_id' => ['$toString' => '$$resp.role_id']]]],
                    "equipments" => 1,
                    "sap_code" => 1,
                    "project_type" => 1,
                    "customer_name" => 1,
                ]]
            ];
            $PlaningDoc = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipline);
            $dataPlaningDoc = array();
            foreach ($PlaningDoc as $doc) \array_push($dataPlaningDoc, $doc);

            // if there is no documentation in the project
            if (\count($dataPlaningDoc) == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "This document dosen't exsit in the project",
                    "data" => []
                ], 404);

            $projectID = $dataPlaningDoc[0]->project_id;

            $cover = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$match' => ['version' => ['$lte' => $dataPlaningDoc[0]->version]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
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
            $userCov = $this->db->selectCollection("ProjectsPlaning")->aggregate($cover);
            $dataCover = array();


            // foreach ($userCov as $cov) {
            //     if (str_ends_with((string)$cov->version, '.00')) {
            //         $description = "Approved";
            //     } else if ((string)$cov->version == '0.01') {
            //         $description = "Create";
            //     } else {
            //         $description = "Edited";
            //     }

            //     $checkApprover = $this->db->selectCollection("ProjectsPlaning")->findOne(['_id' => $this->MongoDBObjectId($PlaningID)]);
            //     if ($checkApprover->is_edit === null) {
            //         $approver = null;
            //     } else {
            //         $approver = $cov->approver;
            //     }
            //     $coverData = [
            //         "project_id"    => $cov->project_id,
            //         "version"       => $cov->version,
            //         "conductor"     => $cov->conductor,
            //         "reviewer"      => $cov->reviewer,
            //         "approver"      => $approver,
            //         "description"   => $description,
            //     ];
            //     array_push($dataCover, $coverData);
            // };

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
                'message' => 'Get Traceability Documentation details successfully !!',
                "data" => [
                    "reportCover" => $dataCover,
                    "reportDetails" => $dataPlaningDoc,

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

    //* [GET] /project-plan/get-doc // Get ducuments for each project_id
    public function GetPlaningDoc(Request $request)
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
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'StatementOfWork']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$StatementOfWork', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "project_name" => 1,
                    "version" => 1,
                    "is_edit" => 1,
                    "status" => 1,
                    "creator_id" => ['$toString' => '$creator_id'],
                    "name_en" => 1,
                    "customer_name" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    "updated_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                [
                    '$group' => [
                        '_id' => [
                            'project_id' => '$project_id', 'project_name' => '$project_name', 'name_en' => '$name_en',
                            'customer_name' => '$customer_name',  'creator_id' => '$creator_id'
                        ],
                        'version' => ['$last' => '$version'], 'is_edit' => ['$last' => '$is_edit'],
                        'status' => ['$last' => '$status'],
                        'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id'],
                        'updated_at' => ['$last' => '$updated_at']
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => '$_id.project_id',
                    "project_name" => '$_id.project_name',
                    "project_plan_id" => '$document_id',
                    "status" => 1,
                    "customer_name" => '$_id.customer_name',
                    "version" => 1,
                    "created_at" => 1,
                    "approved_at" => '$updated_at',
                    "is_edit" => 1,
                    "creator_id" => '$_id.creator_id',
                    "creator_name" => '$_id.name_en'
                ]]
            ];

            $userDoc = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipline);
            $dataUserDoc = array();
            foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);
            return response()->json([
                'status' => 'success',
                'message' => 'Get all Projects Planing Documentation successfully !!',
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

    //* [POST] /project-plan/updated-job-order
    public function updatedJobOrder(Request $request)
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
                'project_plan_id'     => 'required | string',
                'job_order'           => 'required | string',
                'selling_prices'      => 'nullable | numeric',
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


            $projectPlanID  = $request->project_plan_id;
            $jobOrder       = $request->job_order;
            $prices         = $request->selling_prices;

            //!  check data

            $filter = ["_id" => $this->MongoDBObjectId($request->project_plan_id)];

            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->find($filter);

            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project plan id not found", "data" => []], 400);

            //! check data

            $pipeline = [['$sort' => ['created_at' => -1]], ['$limit' => 1], ['$project' => ['_id' => 0, 'project_id' => 1, 'software_requirement' => 1]]];

            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            $seteditfalse = $this->db->selectCollection("ProjectsPlaning")->updateOne(
                ['_id' => $this->MongoDBObjectId($projectPlanID)],
                ['$set' => [
                    "job_order"             => $jobOrder,
                    "selling_prices"        => $prices,
                ]]
            );


            return response()->json([
                "status" => "success",
                "message" => "Updated job order and selling prices in project planing successfully",
                "data" => [],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //! [POST] /project-plan/updated-job-order  Not finish yet
    public function deleteProjectAllCollection(Request $request)
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
                'project_id'     => 'required | string',

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


            $projectID  = $request->project_id;


            //!  check data

            $filter = ["_id" => $this->MongoDBObjectId($request->project_id)];

            $chkProjectID = $this->db->selectCollection("ProjectsPlaning")->find($filter);

            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project plan id not found", "data" => []], 400);

            //! check data

            $pipeline = [['$sort' => ['created_at' => -1]], ['$limit' => 1], ['$project' => ['_id' => 0, 'project_id' => 1, 'software_requirement' => 1]]];

            $result = $this->db->selectCollection("ProjectsPlaning")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            $seteditfalse = $this->db->selectCollection("ProjectsPlaning")->updateOne();


            return response()->json([
                "status" => "success",
                "message" => "Updated job order and selling prices in project planing successfully",
                "data" => [],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
