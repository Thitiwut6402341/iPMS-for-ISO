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

class ProjectController extends Controller
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


    //* [GET] /project/projects-list
    public function projectsList(Request $request)
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
                [
                    '$lookup' => [
                        'from' => 'Accounts',
                        'localField' => 'creater_id',
                        'foreignField' => '_id',
                        'as' => 'Accounts'
                    ]
                ],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                [
                    '$project' => [
                        '_id' => 0,
                        'create_by'             => ['$toString' => '$create_by'],
                        'project_id'            => ['$toString' => '$_id'],
                        'teamspace_id'          => ['$toString' => '$teamspace_id'],
                        'project_type'          => 1,
                        'project_name'          => 1,
                        'project_code'          => 1,
                        'version'               => 1,
                        'customer_contact'      => 1,
                        'customer_name'         => 1,
                        'introduction_of_project' => 1,
                        'list_of_introduction'  => 1,
                        'cost_estimation'       => 1,
                        'phone_number'          => 1,
                        'scope_of_project'      => 1,
                        'objective_of_project'  => 1,
                        'start_date'            => 1,
                        'end_date'              => 1,
                        'approved_by'           => ['$toString' => '$approved_by'],   //! approved_by
                        'approved_date'           => 1,   //! approved_date
                        'create_date'           => 1,
                    ]
                ],

            ];

            $result = $this->db->selectCollection('ProjectsProposal')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "ํYou get all statement of work successfully !!",
                "data" => $data
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] /project/release-new-project
    public function releaseNewProject(Request $request)
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
                'teamspace_id'              => 'required | string',
                'project_type'              => 'required | string |min:1|max:100',
                'project_name'              => 'required | string |min:1|max:255',
                'customer_name'             => 'required | string |min:1|max:255',
                'customer_contact'          => 'required | array',
                'scope_of_project'          => 'required | array',
                'introduction_of_project'   => 'required | string |min:1|max:1000',
                'list_of_introduction'      => 'required | array',
                'objective_of_project'      => 'required | array',
                'cost_estimation'           => 'required | array',
                'sap_code'                  => 'nullable | string',
                'version'                   => 'nullable | string',
                'start_date'                => 'required | string |min:1|max:25',
                'end_date'                  => 'required | string |min:1|max:25',
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

            $decoded                = $jwt->decoded;

            $teamSpaceID            = $request->teamspace_id;
            $projectType            = $request->project_type;
            $projectName            = $request->project_name;
            $customerName           = $request->customer_name;
            $version                = $request->version;

            $customerContact        = $request->customer_contact;
            $costEstimation         = $request->cost_estimation;
            $introduction           = $request->introduction_of_project;
            $listIntroduction       = $request->list_of_introduction;
            $scopes                 = $request->scope_of_project;
            $objectives             = $request->objective_of_project;
            $startDate              = $request->start_date;
            $endDate                = $request->end_date;

            $sapCode                = $request->sap_code;

            $createBy               = $decoded->creater_by;
            $createDate             = date('Y-m-d');
            $approvedBy             = $request->approved_by;
            $approvedAT             = $request->approved_date;

            $year2LastDigit = substr(\date('Y'), 2);
            $month = \date('m');
            $monthYear = $month . "/" . $year2LastDigit;

            $filter = ["month_year" => $monthYear];
            $options = [
                "sort" => ["run_no" => -1],
                "limit" => 1,
            ];
            $result = $this->db->selectCollection("ProjectsRunNo")->find($filter, $options);
            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            $projectCode = "";

            $runNo = 1;
            if (\count($data) === 0) {
                $projectCode = "CoDE/" . $decoded->creater_by . "-" . $monthYear . "-" . \str_pad($runNo, 2, "0", STR_PAD_LEFT);
            } else {
                $info = (object)$data[0];
                $runNo = (int)$info->run_no;
                $runNo += 1;
                $projectCode = "CoDE/" . $decoded->creater_by . "-" . $monthYear . "-" . \str_pad($runNo, 2, "0", STR_PAD_LEFT);
            }
            $this->db->selectCollection("ProjectsRunNo")->insertOne([
                "main_topic"    => "Statement of work",
                "run_no"        => $runNo,
                "month_year"    => $monthYear,
                "project_code"  => $projectCode,
                "run_at"        => $timestamp,
                "run_by_id"     => $this->MongoDBObjectId($decoded->creater_by),
            ]);

            $document = array(
                "create_by"                 => $this->MongoDBObjectId($createBy),
                "teamspace_id"              => $this->MongoDBObjectId($teamSpaceID),
                "project_type"              => $projectType,
                "project_name"              => $projectName,
                "customer_name"             => $customerName,
                "project_code"              => $projectCode,
                "version"                   => $version,
                "customer_contact"          => $customerContact,
                "cost_estimation"           => $costEstimation,
                "sap_code"                  => $sapCode,

                "introduction_of_project"   => $introduction,
                "list_of_introduction"      => $listIntroduction,
                "scope_of_project"          => $scopes,
                "objective_of_project"      => $objectives,
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                "is_edit"                   => null,

                "is_approved"               => false,
                "approved_by"               => $approvedBy,
                "approved_date"             => $approvedAT,
                "create_date"               => $createDate,
                "created_at"                => $timestamp,
            );

            // //!
            // "IsDraft"           => false,
            // "CustomerName"      => $CustomerName,
            // "Phone"             => $Phone,
            // "Email"             => $decoded->Email,
            // "CompanyName"       => $CompanyName,
            // "ExecutiveSummary"  => \is_null($ExecutiveSummary) ? "" : $ExecutiveSummary,
            // "Equipments"        => \is_null($Equipments) ? null : $Equipments,
            // "GroupOfProject"    => \is_null($GroupOfProject) ? null : $GroupOfProject,
            // "PaybackPeriod"     => (float)$PaybackPeriod,
            // "BEP"               => (float)$BEP,
            // "IRR"               => (float)$IRR,
            // "PDF1"              => null,
            // "Image1"            => null,
            // "Image2"            => null,
            // "Image3"            => null,
            // "Image4"            => null,
            // "Image5"            => null,
            // "VideoLink"         => \is_null($VideoLink) ? null : $VideoLink,
            // "TeamCode"          => $decoded->TeamCode,

            // $path = \getcwd() . "\\..\\..\\images\\";

            // if (!\is_dir($path)) \mkdir($path,0777,true);

            // $folderName = $this->randomName(10);
            // $tokenFile = $this->randomName(20);

            // $genDir = $path . $folderName;
            // $document["FolderName"] = $folderName;
            // if (!\is_dir($genDir)) \mkdir($genDir);

            // if (!\is_null($PDF1) && \strlen($PDF1) > 300) {
            //     $fileName = $tokenFile . "-PDF1.pdf";
            //     $document["PDF1"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     $base64 = \trim($PDF1, "data:application/pdf;base64,");
            //     \file_put_contents($genPath, \base64_decode($base64));
            // }

            // if (!\is_null($Image1) && \strlen($Image1) > 300) {
            //     $fileName = $tokenFile . "-Image1.jpg";
            //     $document["Image1"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image1)));
            // }

            // if (!\is_null($Image2) && \strlen($Image2) > 300) {
            //     $fileName = $tokenFile . "-Image2.jpg";
            //     $document["Image2"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image2)));
            // }

            // if (!\is_null($Image3) && \strlen($Image3) > 300) {
            //     $fileName = $tokenFile . "-Image3.jpg";
            //     $document["Image3"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image3)));
            // }

            // if (!\is_null($Image4) && \strlen($Image4) > 300) {
            //     $fileName = $tokenFile . "-Image4.jpg";
            //     $document["Image4"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image4)));
            // }

            // if (!\is_null($Image5) && \strlen($Image5) > 300) {
            //     $fileName = $tokenFile . "-Image5.jpg";
            //     $document["Image5"] = $fileName;
            //     $genPath = $genDir . "\\" . $fileName;
            //     \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image5)));
            // }

            // $filter = ["project_name" => $projectName];
            // $option = [
            //         "projection" => [
            //             "_id" =>1,
            //             "project_id" => ['$toString' => '$_id'],
            //             "project_name" => 1,
            //         ]
            //     ];

            // $chkNameProject = $this->db->selectCollection("ProjectsProposal")->find($filter,$option);

            // $dataChk = array();
            // foreach ($chkNameProject as $doc) \array_push($dataChk, $doc);

            // if (($dataChk) == null)
            // return response()->json([
            //     "status" =>  "error",
            //     "message" => "The project name dose not exist",
            //     "data" => [],
            // ],500);



            //! ./File management on web hosting
            $result = $this->db->selectCollection("ProjectsProposal")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Add new project failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add new statement of work successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //* [PUT] /project/edit-project
    public function editProject(Request $request)
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
                'project_id' => 'required | string |min:1|max:255',
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

            $decoded                = $jwt->decoded;

            $projectID              = $request->project_id;
            $teamSpaceID            = $request->teamSpaceID;
            $projectType            = $request->project_type;
            $projectName            = $request->project_name;
            $customerName           = $request->customer_name;
            $version                = $request->version;     //! need to run auto and following condition

            $sapCode                = $request->sap_code;
            $introduction           = $request->introduction_of_project;
            $listIntroduction       = $request->list_of_introduction;
            $customerContact        = $request->customer_contact;
            $costEstimation         = $request->cost_estimation;
            $scopes                 = $request->scope_of_project;
            $objectives             = $request->objective_of_project;
            $startDate              = $request->start_date;
            $endDate                = $request->end_date;

            // $OKRs               = $request->OKRs;
            // $ExecutiveSummary        = $request->ExecutiveSummary;
            // $Equipments              = $request->Equipments;
            // $GroupOfProject          = $request->GroupOfProject;
            // $PaybackPeriod           = $request->PaybackPeriod;//! float
            // $BEP                     = $request->BEP ;//! float
            // $IRR                     = $request->IRR; //! float
            // $PDF1                    = $request->PDF1; //! base64
            // $Image1                  = $request->Image1; //! base64
            // $Image2                  = $request->Image2; //! base64
            // $Image3                  = $request->Image3 ;//! base64
            // $Image4                  = $request->Image4; //! base64
            // $Image5                  = $request->Image5; //! base64
            // $VideoLink               = $request->VideoLink;
            // $Phone                      = $request->Phone;
            // $CompanyName                = $request->CompanyName;

            $filter = ["_id" => $this->MongoDBObjectId($projectID)]; //, "TeamCode" => $decoded->TeamCode , "IsDraft" => false
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "project_id" => ['$toString' => '$_id'],
                    "project_name" => 1,
                    "teamspace_id" => 1,
                ]
            ];

            $result = $this->db->selectCollection("ProjectsProposal")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "Project ID not found",
                    "data" => [],
                ], 500);

            $update = array(
                "teamspace_id"              => $this->MongoDBObjectId($teamSpaceID),
                "project_type"              => $projectType,
                "project_name"              => $projectName,
                "version"                   => $version,
                "customer_contact"          => $customerContact,
                "cost_estimation"           => $costEstimation,
                "sap_code"                  => $sapCode,
                "customer_name"             => $customerName,

                "introduction_of_project"   => $introduction,
                "list_of_introduction"      => $listIntroduction,
                "scope_of_project"          => $scopes,
                "objective_of_project"      => $objectives,
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                // "is_approved"               => $isApproved,
                // "approved_by"               => $approvedBy,
                // "approved_date"               => $approvedAT,
                "updated_at"                => $timestamp,
            );

            $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Edit statement of work detail successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [DELETE] /project/delete-project
    public function deleteProject(Request $request)
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
                'project_id' => 'required | string',
            ];
            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "state" => false,
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            $projectID = $request->project_id;

            $filter1 = ["_id" => $this->MongoDBObjectId($projectID)];

            $result = $this->db->selectCollection("ProjectsProposal")->deleteOne($filter1);
            if ($result->getDeletedCount() == 0)
                return response()->json(["status" => "error", "message" => "There has been no data deletion", "data" => [],], 500);

            // $filter2 = ["ProjectID" => $this->MongoDBObjectId($ProjectID)]; //, "TeamCode" => $decoded->TeamCode

            // $this->db->selectCollection("ProjectTasksMainTopic")->deleteMany($filter2);
            // $this->db->selectCollection("ProjectTaskSubTopic")->deleteMany($filter2);

            return response()->json([
                "status" => "success",
                "message" => "Delete statement of work successfully",
                "data" => [],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ], 500);
        }
    }

    //! not finished yet
    //* /project/close-project
    public function closeProject(Request $request)
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

            // if (!in_array($decoded->Role, ['owner', 'admin'])) return $this->response->setStatusCode(401)->setJSON(['state' => false, 'msg' => 'Access denied']);
            // if (!\in_array($decoded->Role, ['owner', 'admin'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

            $rules = [
                'ProjectID' => 'required | string',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "state" => false,
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }

            $ProjectID      = $request->ProjectID;

            //! check data
            $filter = ["ProjectID" => $this->MongoDBObjectId($ProjectID)]; //, "TeamCode" => $decoded->TeamCode
            $options = ["limit" => 1];

            $result = $this->db->selectCollection("ProjectTaskSubTopic")->find($filter, $options);
            $data = array();

            //! Comment to waiting for build ProjectTasksubToppic
            //? foreach ($result as $doc) \array_push($data, $doc);
            //? if (\count($data) == 0)
            //?     return response()->json(["status" => "error", "message" => "Can not close the project"]);

            //! check complete
            $filter = ["ProjectID" => $this->MongoDBObjectId($ProjectID), "PercentComplete" => ['$ne' => 100]]; //, "TeamCode" => $decoded->TeamCode
            $options = ["limit" => 1];

            $result = $this->db->selectCollection("ProjectTaskSubTopic")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) > 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Can not close the project",
                    "data" => [],
                ]);


            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            // $timestamp = $this->MongoDBUTCDateTime(\time() * 1000);
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($ProjectID)]; //, "TeamCode" => $decoded->TeamCode
            $update = ["ClosedDT" => $timestamp];

            $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Close the project failed",
                    "data" => []
                ]);

            return response()->json([
                "status" => "success",
                "message" => "ํYou closed the project successfully !!",
                "data" => [$result]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /project/update-scopes
    public function updateScopes(Request $request)
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
                'project_id' => 'required | string',
                'scope_of_project' => 'required | array',
            ];

            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "state" => false,
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }
            $decoded = $jwt->decoded;

            // if (!in_array($decoded->Role, ['owner', 'admin'])) return $this->response->setStatusCode(401)->setJSON(['state' => false, 'msg' => 'Access denied']);
            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

            $projectID          = $request->project_id;
            $scopes             = $request->scope_of_project;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($projectID)]; //, "TeamCode" => $decoded->TeamCode
            $update = ["scope_of_project" => $scopes, "updated_at" => $timestamp];

            $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou update scopes successfully !!",
                "data" => [$update]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /project/update-objectives
    public function updateObjectives(Request $request)
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
                'project_id' => 'required | string',
                'objective_of_project' => 'required | array',
            ];


            $validators = Validator::make($request->all(), $rules);

            if ($validators->fails()) {
                return response()->json([
                    "status" => "error",
                    "state" => false,
                    "message" => "Bad request",
                    "data" => [
                        [
                            "validator" => $validators->errors()
                        ]
                    ]
                ], 400);
            }
            $decoded = $jwt->decoded;

            // if (!in_array($decoded->Role, ['owner', 'admin'])) return $this->response->setStatusCode(401)->setJSON(['state' => false, 'msg' => 'Access denied']);
            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

            $projectID          = $request->project_id;
            $objectives         = $request->objective_of_project;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($projectID)]; //, "TeamCode" => $decoded->TeamCode
            $update = ["objective_of_project" => $objectives, "updated_at" => $timestamp];

            $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ]);

            return response()->json([
                "status" => "success",
                "message" => "ํYou update Objectives successfully !!",
                "data" => [$update]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [POST] /project/verified-statement-of-work
    public function verifiedProject(Request $request)
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
                'project_id'          => 'required | string | min:1 | max:255',
                "approved_by"         => ['nullable', 'string'],
                "is_approved"         => ['required', 'boolean'],
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

            $projectID  = $request->project_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($projectID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "project_id" => ['$toString' => '$_id'], "is_approved" => 1,]];

            $chkProjectID = $this->db->selectCollection("ProjectsProposal")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project id not found", "data" => []], 200);
            //! check data

            $isApproved     = $request->is_approved;

            $update = [
                "is_approved"               => $isApproved,
                "approved_by"               => $this->MongoDBObjectId($decoded->creater_by),
                "approved_date"             => date('Y-m-d'),
                "is_approved_at"            => $timestamp,
            ];
            $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Approved statement of work successfully !!",
                "data" => [$result]
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





    // //* /project/new-draft-project
    // public function newDraftProject(Request $request)
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

    //         // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

    //         $rules = [
    //             'ProjectName' => 'required | string |min:1|max:255',
    //             'ProjectSapID' => 'required | string |min:1|max:255',
    //             // 'Budget' => 'required | int',
    //             'OKRs' => 'required | array',
    //             'Scopes' => 'required | array',
    //             'Requirements' => 'required | array',
    //             'Notes' => 'required | string |min:1|max:255',
    //             'TeamSpaceID' => 'required | string',
    //             // 'K2Code' => 'required | string ',
    //             // 'PRCode' => 'required | string ',
    //             // 'POCode' => 'required | string ',
    //             // 'Revenue' => 'required | int ',
    //             // 'COGS' => 'required | int',
    //         ];

    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators -> fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators -> errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }

    //         \date_default_timezone_set('Asia/Bangkok');
    //         $date = date('Y-m-d H:i:s');
    //         $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp()+2.52e4)*1000);

    //         $decoded = $jwt->decoded;

    //         $ProjectName        = $request->ProjectName;
    //         $ProjectSapID       = $request->ProjectSapID;
    //         // $Budget             = $request->Budget;
    //         $OKRs               = $request->OKRs;
    //         $Notes              = $request->Notes;
    //         $TeamSpaceID        = $request->TeamSpaceID;
    //         // $K2Code             = $request->K2Code;
    //         // $PRCode             = $request->PRCode;
    //         // $POCode             = $request->POCode;
    //         // $Revenue            = $request->Revenue;
    //         // $COGS               = $request->COGS;
    //         $IsDraft            = $request ->IsDraft;

    //         // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

    //         // $ExecutiveSummary        = $request->ExecutiveSummary;
    //         $Equipments              = $request->Equipments;
    //         //! type of Equipments = {name: string, unit: string, price: number, quantity: number, note: string}[]
    //         $GroupOfProject          = $request->GroupOfProject;
    //         //! {topic: number, label: string, isChecked: boolean}[]
    //         $PaybackPeriod           = $request->PaybackPeriod;//! float
    //         // $BEP                     = $request->BEP ;//! float
    //         // $IRR                     = $request->IRR; //! float
    //         $PDF1                    = $request->PDF1; //! base64
    //         $Image1                  = $request->Image1; //! base64
    //         $Image2                  = $request->Image2; //! base64
    //         $Image3                  = $request->Image3 ;//! base64
    //         $Image4                  = $request->Image4; //! base64
    //         $Image5                  = $request->Image5; //! base64
    //         $VideoLink               = $request->VideoLink;

    //         //! New requsets (not validate) for ISO

    //         $Scopes                 = $request->Scopes; //! array
    //         $Requirements           = $request->Requirements; //! array
    //         $CustomerName           = $request->CustomerName; //! object en:th
    //         $Phone                  = $request->Phone;
    //         $CompanyName            = $request->CompanyName;

    //         $year2LastDigit = substr(\date('Y'), 2);
    //         $month = \date('m');
    //         $monthYear = $month . "/" . $year2LastDigit;

    //         $filter = [ "MonthYear" => $monthYear];  //"TeamCode" => $decoded->TeamCode,
    //         $options = [
    //             "sort" => ["RunNo" => -1],
    //             "limit" => 1,
    //         ];

    //         $result = $this->db->selectCollection("ProjectsRunNo")->find($filter,$options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         $ProjectCode = "";
    //         // $TeamSpaceCode = "";
    //         $RunNo = 1;

    //         if (\count($data) === 0) {
    //             $ProjectCode = "SNC/" . $decoded->CreaterID . "-" . $monthYear . "-" . \str_pad($RunNo, 2, "0", STR_PAD_LEFT);
    //             // $TeamSpaceCode = \str_pad($RunNo, 4, "000", STR_PAD_LEFT);
    //         } else {
    //             $info = (object)$data[0];
    //             $RunNo = (int)$info->RunNo;
    //             $RunNo += 1;
    //             $ProjectCode = "SNC/" . $decoded->CreaterID . "-" . $monthYear . "-" . \str_pad($RunNo, 2, "0", STR_PAD_LEFT);
    //             // $TeamSpaceCode = \str_pad($RunNo, 4, "000", STR_PAD_LEFT);

    //         }

    //         $timestamp = $this->MongoDBUTCDateTime(\time() * 1000);
    //         $this->db->selectCollection("ProjectsRunNo")->insertOne([
    //             "RunNo"        => $RunNo,
    //             "MonthYear"    => $monthYear,
    //             "ProjectCode"  => $ProjectCode,
    //             // "TeamSpaceCode"  => $TeamSpaceCode,
    //             "RunDT"        => $timestamp,
    //             "RunByID"      => $this->MongoDBObjectId($decoded->CreaterID),
    //             // "TeamCode"     => $decoded->TeamCode,
    //         ]);

    //         $document = array(
    //             "ProjectName"       => $ProjectName,
    //             "ProjectCode"       => $ProjectCode,
    //             // "TeamSpaceCode"       => $TeamSpaceCode,
    //             "ProjectSapID"       => $ProjectSapID,  //* New
    //             // "Budget"            => (float)$Budget,
    //             "OKRs"              => $OKRs,
    //             "Scopes"            => $Scopes,         //* New
    //             "Requirements"      => $Requirements,   //* New
    //             "Notes"             => $Notes,
    //             "TeamSpaceID"       => $this->MongoDBObjectId($TeamSpaceID),
    //             // "K2Code"            => $K2Code,
    //             // "PRCode"            => $PRCode,
    //             // "POCode"            => $POCode,
    //             // "Revenue"           => (float)$Revenue, //* New
    //             // "COGS"              => (float)$COGS,    //* New
    //             "IsDraft"           => true,
    //             "CustomerName"      => $CustomerName,       //* New
    //             "Phone"             => $Phone,               //* New
    //             "Email"             => $decoded->Email,      //* New
    //             "CompanyName"       => $CompanyName,      //* New

    //             //!
    //             // "ExecutiveSummary"  => \is_null($ExecutiveSummary) ? "" : $ExecutiveSummary,
    //             "Equipments"        => \is_null($Equipments) ? null : $Equipments,
    //             "GroupOfProject"    => \is_null($GroupOfProject) ? null : $GroupOfProject,
    //             "PaybackPeriod"     => (float)$PaybackPeriod,
    //             // "BEP"               => (float)$BEP,
    //             // "IRR"               => (float)$IRR,
    //             "PDF1"              => null,
    //             "Image1"            => null,
    //             "Image2"            => null,
    //             "Image3"            => null,
    //             "Image4"            => null,
    //             "Image5"            => null,
    //             "VideoLink"         => \is_null($VideoLink) ? null : $VideoLink,

    //             //!
    //             "CreatedDT"         => $timestamp,
    //             "ReleasedDT"        => $timestamp,
    //             "ClosedDT"          => null,
    //             "CreaterID"         => $this->MongoDBObjectId($decoded->CreaterID),
    //             // "TeamCode"          => $decoded->TeamCode,
    //         );

    //         $path = \getcwd() . "\\..\\..\\images\\";

    //         if (!\is_dir($path)) \mkdir($path,0777,true);

    //         // $now = new \DateTime();
    //         $folderName = $this->randomName(10) ;
    //         $tokenFile = $this->randomName(20);

    //         $genDir = $path . $folderName;
    //         $document["FolderName"] = $folderName;
    //         if (!\is_dir($genDir)) \mkdir($genDir);

    //         if (!\is_null($PDF1) && \strlen($PDF1) > 300) {
    //             $fileName = $tokenFile . "-PDF1.pdf";
    //             $document["PDF1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             $base64 = \trim($PDF1, "data:application/pdf;base64,");
    //             \file_put_contents($genPath, \base64_decode($base64));
    //         }

    //         if (!\is_null($Image1) && \strlen($Image1) > 300) {
    //             $fileName = $tokenFile . "-Image1.jpg";
    //             $document["Image1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image1)));
    //         }

    //         if (!\is_null($Image2) && \strlen($Image2) > 300) {
    //             $fileName = $tokenFile . "-Image2.jpg";
    //             $document["Image2"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image2)));
    //         }

    //         if (!\is_null($Image3) && \strlen($Image3) > 300) {
    //             $fileName = $tokenFile . "-Image3.jpg";
    //             $document["Image3"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image3)));
    //         }

    //         if (!\is_null($Image4) && \strlen($Image4) > 300) {
    //             $fileName = $tokenFile . "-Image4.jpg";
    //             $document["Image4"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image4)));
    //         }

    //         if (!\is_null($Image5) && \strlen($Image5) > 300) {
    //             $fileName = $tokenFile . "-Image5.jpg";
    //             $document["Image5"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image5)));
    //         }
    //         //! ./File management on web hosting

    //         $result = $this->db->selectCollection("ProjectsProposal")->insertOne($document);

    //         if ($result->getInsertedCount() == 0)
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "Add teamespace failed",
    //                 "data" => [$result]
    //             ],200);

    //         return response() -> json([
    //             "status" => "success",
    //             "message" => "ํYou create new Draft project successfully !!",
    //             "data" => [$result]
    //         ],200);


    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }

    // //* /project/edit-draft-project
    // public function editDraftProject(Request $request)
    // {
    //     try {
    //         //! JWT
    //          $header = $request->header('Authorization');
    //         $jwt = $this->jwtUtils->verifyToken($header);
    //         if (!$jwt->state) return response()->json([
    //             "status" => "error",
    //             "message" => "Unauthorized",
    //             "data" => [],
    //         ], 401);
    //         $decoded = $jwt->decoded;

    //         // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

    //         $rules = [
    //             'ProjectID' => 'required | string |min:1|max:255',
    //             // 'ProjectName' => 'required | string |min:1|max:255',
    //         ];

    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators -> fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators -> errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }

    //         $ProjectID       = $request->ProjectID;
    //         $ProjectName        = $request->ProjectName;
    //         // $Budget             = $request->Budget;
    //         $OKRs               = $request->OKRs;
    //         $Notes              = $request->Notes;
    //         // $TeamSpaceID        = $request->TeamSpaceID;
    //         // $K2Code             = $request->K2Code;
    //         // $PRCode             = $request->PRCode;
    //         // $POCode             = $request->POCode;
    //         // $Revenue            = $request->Revenue;
    //         // $COGS               = $request->COGS;

    //         // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

    //         // $ExecutiveSummary        = $request->ExecutiveSummary;
    //         $Equipments              = $request->Equipments;
    //         //! type of Equipments = {name: string, unit: string, price: number, quantity: number, note: string}[]
    //         $GroupOfProject          = $request->GroupOfProject;
    //         //! {topic: number, label: string, isChecked: boolean}[]
    //         $PaybackPeriod           = $request->PaybackPeriod;//! float
    //         // $BEP                     = $request->BEP ;//! float
    //         // $IRR                     = $request->IRR; //! float
    //         $PDF1                    = $request->PDF1; //! base64
    //         $Image1                  = $request->Image1; //! base64
    //         $Image2                  = $request->Image2; //! base64
    //         $Image3                  = $request->Image3 ;//! base64
    //         $Image4                  = $request->Image4; //! base64
    //         $Image5                  = $request->Image5; //! base64
    //         $VideoLink               = $request->VideoLink;

    //         //! New requsets (not validate) for ISO

    //         $Scopes                 = $request->Scopes; //! array
    //         $Requirements           = $request->Requirements; //! array
    //         $CustomerName           = $request->CustomerName; //! object en:th
    //         $Phone                  = $request->Phone;
    //         $CompanyName            = $request->CompanyName;

    //         \date_default_timezone_set('Asia/Bangkok');
    //         $date = date('Y-m-d H:i:s');
    //         $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp()+2.52e4)*1000);

    //         $filter = ["_id" => $this->MongoDBObjectId($ProjectID), "IsDraft" => true]; //, "TeamCode" => $decoded->TeamCode
    //         $options = [
    //             "limit" => 1,
    //             "projection" => [
    //                     "_id" => 0,
    //                     "ProjectID" => ['$toString' => '$_id'],
    //                     "ProjectName"=>1,
    //                     "FolderName"=>1,
    //             ]
    //         ];

    //         $result = $this->db->selectCollection("ProjectsProposal")->find($filter, $options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         if (\count($data) == 0)
    //         return response()->json(["status" => "error", "message" => "ProjectID not found" , "data"=> []],200);

    //         $info = (object)$data[0];
    //         $folderName = $info->FolderName;

    //         $update = array(
    //             "ProjectName"       => $ProjectName,
    //             // "Budget"            => (float)$Budget,
    //             "OKRs"              => $OKRs,
    //             "Scopes"              => $Scopes,
    //             "Requirements"              => $Requirements,
    //             "Notes"             => $Notes,
    //             // "TeamSpaceID"       => $this->MongoDBObjectId($TeamSpaceID),
    //             // "K2Code"            => $K2Code,
    //             // "PRCode"            => $PRCode,
    //             // "POCode"            => $POCode,
    //             // "Revenue"           => (float)$Revenue, //* New
    //             // "COGS"              => (float)$COGS, //* New
    //             "CustomerName"      => $CustomerName,       //* New
    //             "Phone"             => $Phone,               //* New
    //             "Email"             => $decoded->Email,      //* New
    //             "CompanyName"       => $CompanyName,      //* New
    //             "IsDraft"           => true,
    //             //!
    //             // "ExecutiveSummary"  => \is_null($ExecutiveSummary) ? "" : $ExecutiveSummary,
    //             "Equipments"        => \is_null($Equipments) ? null : $Equipments,
    //             "GroupOfProject"    => \is_null($GroupOfProject) ? null : $GroupOfProject,
    //             // "PaybackPeriod"     => (float)$PaybackPeriod,
    //             // "BEP"               => (float)$BEP,
    //             // "IRR"               => (float)$IRR,
    //             "VideoLink"         => \is_null($VideoLink) ? null : $VideoLink,
    //              //!
    //              "UpdatedDT"         => $timestamp,
    //              "ReleasedDT"        => $timestamp,
    //              "ClosedDT"          => null,
    //              "CreaterID"         => $this->MongoDBObjectId($decoded->CreaterID),
    //              // "TeamCode"          => $decoded->TeamCode,

    //         );

    //         $path = \getcwd() . "\\..\\..\\images\\";

    //         if (!\is_dir($path)) \mkdir($path,0777,true);

    //         $tokenFile = $this->randomName(20);

    //         $genDir = $path . $folderName;
    //         if (!\is_dir($genDir)) \mkdir($genDir);

    //         if (!\is_null($PDF1) && \strlen($PDF1) > 300) {
    //             $fileName = $tokenFile . "-PDF1.pdf";
    //             $update["PDF1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             $base64 = \trim($PDF1, "data:application/pdf;base64,");
    //             \file_put_contents($genPath, \base64_decode($base64));
    //         }

    //         if (!\is_null($Image1) && \strlen($Image1) > 300) {
    //             $fileName = $tokenFile . "-Image1.jpg";
    //             $update["Image1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image1)));
    //         }

    //         if (!\is_null($Image2) && \strlen($Image2) > 300) {
    //             $fileName = $tokenFile . "-Image2.jpg";
    //             $update["Image2"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image2)));
    //         }

    //         if (!\is_null($Image3) && \strlen($Image3) > 300) {
    //             $fileName = $tokenFile . "-Image3.jpg";
    //             $update["Image3"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image3)));
    //         }

    //         if (!\is_null($Image4) && \strlen($Image4) > 300) {
    //             $fileName = $tokenFile . "-Image4.jpg";
    //             $update["Image4"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image4)));
    //         }

    //         if (!\is_null($Image5) && \strlen($Image5) > 300) {
    //             $fileName = $tokenFile . "-Image5.jpg";
    //             $update["Image5"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image5)));
    //         }

    //         //! ./File management on web hosting

    //         $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

    //         // return response()->json([$result->getModifiedCount()]);

    //         if ($result->getModifiedCount() == 0)
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "There has been no data modification",
    //                 "data" => []
    //             ],200);

    //         return response()->json([
    //             "status" => "success",
    //             "message" => "Edit draft project successfully",
    //             "data"=>[]
    //         ],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }

    // //* /project/ release-draft-project
    // public function releaseDraftProject(Request $request)
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

    //         // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

    //         $rules = [
    //             'ProjectID' => 'required | string |min:1|max:255',
    //             // 'ProjectName' => 'required | string |min:1|max:255',
    //         ];

    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators -> fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators -> errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }

    //         $ProjectID       = $request->ProjectID;

    //         $PDF1                    = $request->PDF1;   //! base64
    //         $Image1                  = $request->Image1; //! base64
    //         $Image2                  = $request->Image2; //! base64
    //         $Image3                  = $request->Image3 ;//! base64
    //         $Image4                  = $request->Image4; //! base64
    //         $Image5                  = $request->Image5; //! base64
    //         $VideoLink               = $request->VideoLink;

    //         \date_default_timezone_set('Asia/Bangkok');
    //         $date = date('Y-m-d H:i:s');
    //         $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4)*1000);

    //         $filter = ["_id" => $this->MongoDBObjectId($ProjectID), "IsDraft" => true]; //, "TeamCode" => $decoded->TeamCode
    //         $options = [
    //             "limit" => 1,
    //             "projection" => [
    //                     "_id" => 0,
    //                     "ProjectID" => ['$toString' => '$_id'],
    //                     "ProjectName"=>1,
    //                     "FolderName"=>1,
    //             ]
    //         ];

    //         $result = $this->db->selectCollection("ProjectsProposal")->find($filter, $options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         if (\count($data) == 0)
    //         return response()->json(["status" => "error", "message" => "ProjectID not found" , "data"=> []],200);

    //         $info = (object)$data[0];
    //         $folderName = $info->FolderName;

    //         $update = array(
    //             "IsDraft"           => false,
    //             "UpdatedDT"         => $timestamp,
    //             "ClosedDT"          => null,
    //             "CreaterID"         => $this->MongoDBObjectId($decoded->CreaterID),
    //             // "TeamCode"          => $decoded->TeamCode,
    //         );

    //         $path = \getcwd() . "\\..\\..\\images\\";

    //         if (!\is_dir($path)) \mkdir($path,0777,true);

    //         $tokenFile = $this->randomName(20);

    //         $genDir = $path . $folderName;
    //         if (!\is_dir($genDir)) \mkdir($genDir);

    //         if (!\is_null($PDF1) && \strlen($PDF1) > 300) {
    //             $fileName = $tokenFile . "-PDF1.pdf";
    //             $update["PDF1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             $base64 = \trim($PDF1, "data:application/pdf;base64,");
    //             \file_put_contents($genPath, \base64_decode($base64));
    //         }

    //         if (!\is_null($Image1) && \strlen($Image1) > 300) {
    //             $fileName = $tokenFile . "-Image1.jpg";
    //             $update["Image1"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image1)));
    //         }

    //         if (!\is_null($Image2) && \strlen($Image2) > 300) {
    //             $fileName = $tokenFile . "-Image2.jpg";
    //             $update["Image2"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image2)));
    //         }

    //         if (!\is_null($Image3) && \strlen($Image3) > 300) {
    //             $fileName = $tokenFile . "-Image3.jpg";
    //             $update["Image3"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image3)));
    //         }

    //         if (!\is_null($Image4) && \strlen($Image4) > 300) {
    //             $fileName = $tokenFile . "-Image4.jpg";
    //             $update["Image4"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image4)));
    //         }

    //         if (!\is_null($Image5) && \strlen($Image5) > 300) {
    //             $fileName = $tokenFile . "-Image5.jpg";
    //             $update["Image5"] = $fileName;
    //             $genPath = $genDir . "\\" . $fileName;
    //             \file_put_contents($genPath, \base64_decode(\preg_replace('#^data:image/\w+;base64,#i', '', $Image5)));
    //         }

    //         //! ./File management on web hosting

    //         $result = $this->db->selectCollection("ProjectsProposal")->updateOne($filter, ['$set' => $update]);

    //         if ($result->getModifiedCount() == 0)
    //             return response()->json(["status" => "error", "message" => "Release draft project failed", "data"=>[]],200);

    //         return response()->json(["status" => "success", "message" => "You release draft project successfully", "data"=>[]],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }


}
