<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use Illuminate\Validation\Rule;
use App\Http\Libraries\Bcrypt;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AccountsController extends Controller
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

    //* [GET] /account/roles
    public function roles(Request $request)
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

            $data = [
                ["Role" => "super-admin", "Description" => "1.can monitor all employees in team. 2.can approved or reject project. 3.can evaluate performance of employes. and similar to regular users,
                                                             "],
                ["Role" => "admin", "Description" => "Admin"],
                ["Role" => "user", "Description" => "User"],
                ["Role" => "owner", "Description" => "Can create Project and invite other in your Teamspace"],
            ];


            return response()->json([
                "status" => "success",
                "message" => "ํYou get roles successfully !!",
                "data" => [$data]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ]);
        }
    }

    //* [GET] /account/get-accounts-list
    public function accountsList(Request $request)
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
            $datetime = date('Y-m-d H:i:s');
            $date = date('Y-m-d');

            // $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($datetime))->getTimestamp()+2.52e4)*1000);

            $endDatetime = new \DateTime($date);
            $endTimestamp = ($endDatetime->getTimestamp() + 2.52e4) * 1000;

            $pipeline3 = [
                ['$lookup' => ['from' => 'Teams', 'localField' => 'team_id', 'foreignField' => '_id', 'as' => 'Teams']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Teams', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'Positions']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Positions', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'UniversityList', 'localField' => 'university_id', 'foreignField' => 'UniversityID', 'as' => 'UniversityList']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$UniversityList', 0]], '$$ROOT']]]],
                [
                    '$project' =>
                    [
                        '_id' => 0, 'account_id' => ['$toString' => '$_id'], 'team_id' => ['$toString' => '$team_id'], 'position_id' => ['$toString' => '$position_id'], 'user_id' => ['$toString' => '$user_id'], 'team' => '$Team', 'team_desc' => '$TeamDescription', 'position' => '$Position', 'position_desc' => '$PositionDescription',
                        'name_th' => 1, 'name_en' => 1, 'employees_id' => 1, 'work_start_date' => 1, 'date_of_birth' => 1, 'telephone' => 1, 'personal_email' => 1, 'official_email' => 1,
                        'picture' => 1, 'emergency_contact' => 1, 'is_graduated' => 1, 'graduated_year' => 1, 'education_level' => 1, 'university_id' => 1, 'university_name' => '$UniversityName',
                        'major' => 1, 'school_of' => 1, 'current_gpax' => 1, 'resume_docs' => 1, 'job_desc_docs' => 1, 'hr_docs' => 1, 'graduated_gpax' => 1,
                        'work_month' => ['$dateDiff' => ['startDate' => ['$toDate' => '$work_start_date'], 'endDate' => $this->MongoDBUTCDateTime($endTimestamp), 'unit' => 'month', 'timezone' => 'Asia/Bangkok']],
                        'ages_month' => ['$dateDiff' => ['startDate' => ['$toDate' => '$date_of_birth'], 'endDate' => $this->MongoDBUTCDateTime($endTimestamp), 'unit' => 'month', 'timezone' => 'Asia/Bangkok']]
                    ]
                ]
            ];

            $result = $this->db->selectCollection("Accounts")->aggregate($pipeline3);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            // return response()->json([$data]);

            return response()->json([
                "status" => "success",
                "message" => "Get all accounts list successfully",
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }

    // //* [GET] /accounts/template-accounts
    // public function templateAccounts(Request $request)
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


    //         $rules = [
    //             'user_id'                => 'required | string |min:1|max:50',
    //         ];

    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators->fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators->errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }

    //         $decoded        = $jwt->decoded;
    //         $CreaterID      = $decoded->creater_by;


    //         \date_default_timezone_set('Asia/Bangkok');
    //         $date = date('Y-m-d H:i:s');
    //         $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


    //         $filter = ["_id" => $this->MongoDBObjectId($CreaterID)];
    //         $option = ["projection" => ["_id" => 0, "creater_by" => ['$toString' => '$_id'], "name" => 1, "email" => 1, "password" => 1,]];

    //         $queryPassword = $this->db->selectCollection("Users")->findOne($filter, $option);

    //         // return response()->json($queryPassword);


    //         $document = [
    //             "user_id"                   => $decoded->user_id,
    //             "username"                  => $decoded->email,
    //             "role"                      => "user",
    //             "team_id"                   => null,
    //             "position_id"               => null,
    //             "name_th"                   => null,
    //             "name_en"                   => $decoded->name,
    //             "employees_id"              => null,
    //             "password"                  => null,
    //             "work_start_date"           => null,
    //             "date_of_birth"             => null,
    //             "telephone"                 => null,
    //             "personal_email"            => null,
    //             "official_email"            => null,
    //             "picture"                   => null,
    //             "emergency_contact"         => null,
    //             "is_graduated"              => null,
    //             "graduated_year"           => null,
    //             "education_level"           => null,
    //             "university_id"             => null,
    //             "UniversityName"            => null,
    //             "major"                     => null,
    //             "school_of"                 => null,
    //             "graduated_gpax"            => null,
    //             "current_gpax"              => null,
    //             "resume_docs"                => null,
    //             "job_desc_docs"              => null,
    //             "hr_docs"                    => null,
    //             "created_at"                 => $timestamp,
    //             "updated_at"                 => $timestamp,
    //         ];

    //         $result = $this->db->selectCollection("Users")->find($document);

    //         $dataResult = [];
    //         foreach ($result as $doc) \array_push($dataResult, $doc);

    //         // $updateIsAccount = $this->db->selectCollection("Users")->updateOne($filter, ['$set' => ['is_account' => true]]);

    //         return response()->json([
    //             "status" => "success",
    //             "message" => "Get Template accounts successfully",
    //             "data" => $dataResult
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => "error",
    //             "message" => $e->getMessage(),
    //             "data" => []
    //         ]);
    //     }
    // }


    //* [POST] /accounts/add-accounts
    public function addAccounts(Request $request)
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
                'user_id'                => 'required | string |min:1|max:50',
                'team_id'                => 'nullable | string |min:1|max:50',
                'position_id'            => 'required | string |min:1|max:50',
                'name_th'                => 'required | string |min:1|max:100',
                'name_en'                => 'required | string |min:1|max:100',
                'employees_id'           => 'required | string |min:1|max:20',
                'work_start_date'        => 'required | string |min:1|max:20',
                'date_of_birth'          => 'required | string |min:1|max:20',
                'telephone'              => 'required | string |min:1|max:15',
                'personal_email'         => 'nullable | string |min:1|max:50',
                'official_email'         => 'required | string |min:1|max:50',
                'picture'                => 'nullable | string ',
                'emergency_contact'      => 'nullable | array ',
                'is_graduated'           => 'nullable | boolean ',
                'graduated_year'         => ['nullable', 'string'],
                'education_level'        => ['array'],
                'university_id'          => 'nullable | int ',
                'major'                  => 'nullable | string |min:1|max:50',
                'school_of'              => 'nullable | string |min:1|max:50',
                'graduated_gpax'         => ['nullable', 'string'],
                'current_gpax'           => ['nullable', 'string'],
                'resume_docs'            => 'required | string ',
                'job_desc_docs'          => 'nullable | string ',
                'hr_docs'                => 'nullable | string ',
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

            //! check data

            $filter1 = ["_id" => $this->MongoDBObjectId($request->user_id)];
            $options1 = ["limit" => 1, "projection" => ["_id" => 0, "user_id" => ['$toString' => '$_id'], "name" => 1, "email" => 1, "role" => 1, "password" => 1]];
            $chkUserID      = $this->db->selectCollection("Users")->find($filter1, $options1);

            $dataChkUserID = array();
            foreach ($chkUserID as $doc) \array_push($dataChkUserID, $doc);

            // return response()->json($dataChkUserID[0]);

            if (\count($dataChkUserID) == 0)
                return response()->json(["status" => "error", "message" => "User dose not exist in system", "data" => []], 500);

            $chkTeamID = $this->db->selectCollection("Teams")->find(["_id" => $this->MongoDBObjectId($request->team_id)], ["projection" => ["_id" => 0, "team_id" => ['$toString' => '$_id'], "Team" => 1, "TeamDescription" => 1,]]);
            $chkPositionID = $this->db->selectCollection("Positions")->find(["_id" => $this->MongoDBObjectId($request->position_id)], ["projection" => ["_id" => 0, "position_id" => ['$toString' => '$_id'], "Position" => 1, "PositionDescription" => 1,]]);

            $dataChkTeamID = array();
            foreach ($chkTeamID as $doc) \array_push($dataChkTeamID, $doc);

            if (\count($dataChkTeamID) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "Team ID does not found",
                    "data" => []
                ]);
            };

            $dataChkPositionID = array();
            foreach ($chkPositionID as $doc) \array_push($dataChkPositionID, $doc);

            if (\count($dataChkPositionID) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "Position ID does not found",
                    "data" => []
                ]);
            };
            //! check data

            $CreaterID              = $decoded->user_id;
            $teamID                 = $this->MongoDBObjectId($request->team_id);
            $positionID             = $this->MongoDBObjectId($request->position_id);
            $nameTH                 = $request->name_th;
            $nameEN                 = $request->name_en;
            $employeeID             = $request->employees_id;
            $workStartDate           = $request->work_start_date;
            $dateOfBirth             = $request->date_of_birth;
            $telephone               = $request->telephone;
            $personalEmail           = $request->personal_email;
            $officialEmail           = $request->official_email;
            $picture                 = $request->picture;
            $emergencyContact        = $request->emergency_contact;
            $isGraduated             = $request->is_graduated;
            $greaduatedYear          = $request->graduated_year;
            $educationLevel            = $request->education_level;
            $universityID              = $request->university_id;
            $major                     = $request->major;
            $schoolOf                  = $request->school_of;
            $graduatedGPAX             = $request->graduated_gpax;
            $currentGPAX               = $request->current_gpax;
            $resumeDocs                = $request->resume_docs;
            $jobDocs                   = $request->job_desc_docs;         //* not require
            $hrDocs                    = $request->hr_docs;              //* not require

            $queryUniversityName    = $this->db->selectCollection("UniversityList")->findOne(["UniversityID" => $universityID], ["projection" => ["_id" => 0, "UniversityName" => 1, "UniversityAbbreviation" => 1,]]);
            $universityName         = $queryUniversityName->UniversityName;
            $WorkStartDate = \strlen($workStartDate) == 0 ? \date('Y-m-d') : $workStartDate;

            $decoded = $jwt->decoded;
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


            $path = getcwd() . "\\..\\documents\\Accounts\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/documents/Accounts/"; //server

            //! check data
            $filter = ['user_id' => $this->MongoDBObjectId($request->user_id)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "account_id" => ['$toString' => '$_id'],
                    "picture" => 1,
                    "resume_docs" => 1,
                    "job_desc_docs" => 1,
                    "hr_docs" => 1,
                ]
            ];

            $resultOldDocs = $this->db->selectCollection("Accounts")->find($filter, $options);

            $dataOldDocs = array();
            foreach ($resultOldDocs as $doc) \array_push($dataOldDocs, $doc);

            if ($resumeDocs !== null) {
                if (str_starts_with($resumeDocs, 'data:application/pdf;base64,')) {
                    $fileNameResume = "Resume" . "_" . $timestamp . ".pdf";
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileNameResume, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $resumeDocs)));
                    $resumeDocs = $pathUsed . $fileNameResume;
                }
            }


            if ($jobDocs !== null) {
                if (str_starts_with($jobDocs, 'data:application/pdf;base64,')) {
                    $fileNameJob = "Jobdescription" . "_" . $timestamp . ".pdf";
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileNameJob, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $jobDocs)));
                    $jobDocs = $pathUsed . $fileNameJob;
                }
            }

            if ($hrDocs !== null) {
                if (str_starts_with($hrDocs, 'data:application/pdf;base64,')) {
                    $fileNameHR = "HR_documentation" . "_" . $timestamp . ".pdf";
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileNameHR, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $hrDocs)));
                    $hrDocs = $pathUsed . $fileNameHR;
                }
            }



            $document = [
                "user_id"                   => $this->MongoDBObjectId($request->user_id),
                "username"                  => $dataChkUserID[0]->email,
                "role"                      => "user",
                "team_id"                   => $this->MongoDBObjectId($teamID),
                "position_id"               => $this->MongoDBObjectId($positionID),
                "name_th"                   => $nameTH,
                "name_en"                   => $nameEN,
                "employees_id"              => $employeeID,
                "password"                  => $dataChkUserID[0]->password,
                "work_start_date"           => $WorkStartDate,
                "date_of_birth"             => \strlen($dateOfBirth) == 0 ? "1996-01-01" : $dateOfBirth,
                "telephone"                 => $telephone,
                "personal_email"            => $personalEmail,
                "official_email"            => $officialEmail,
                "picture"                   => $picture,
                "emergency_contact"         => $emergencyContact,
                "is_graduated"              => $isGraduated,
                "graduated_year"            => $greaduatedYear,
                "education_level"           => $educationLevel,
                "university_id"             => (int)$universityID,
                "UniversityName"            => $universityName,
                "major"                     => $major,
                "school_of"                 => $schoolOf,
                "graduated_gpax"            => $graduatedGPAX,
                "current_gpax"              => $currentGPAX,
                "resume_docs"               => $resumeDocs,
                "job_desc_docs"             => $jobDocs,
                "hr_docs"                   => $hrDocs,
                "login_at"                  => null,
                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,
            ];

            //! Check Data in account
            $filter2 = ["user_id" => $this->MongoDBObjectId($request->user_id)];
            $options2 = ["limit" => 1, "projection" => ["_id" => 0, "user_id" => ['$toString' => '$user_id']]];
            $chkAccountID      = $this->db->selectCollection("Accounts")->find($filter2, $options2);

            $dataChkAccountID = array();
            foreach ($chkAccountID as $doc) \array_push($dataChkAccountID, $doc);

            // return response()->json(count($dataChkAccountID));

            if (count($dataChkAccountID) != 0) {
                $result = $this->db->selectCollection("Accounts")->updateOne($filter2, ['$set' => $document]);

                return response()->json([
                    "status" => "success",
                    "message" => "Updated accounts successfully",
                    "data" => []
                ]);
            } else {
                $result = $this->db->selectCollection("Accounts")->insertOne($document);
                $updateIsAccount = $this->db->selectCollection("Users")->updateOne($filter2, ['$set' => ['is_account' => true]]);

                return response()->json([
                    "status" => "success",
                    "message" => "Add new accounts successfully",
                    "data" => []
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }


    //* [PUT] /account/edit-accounts
    public function editAccounts(Request $request)
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

            $rules = [
                'account_id'   => 'nullable | string',
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

            $accountID               = $request->account_id;
            $teamID                  = $request->team_id;
            $positionID              = $request->position_id;
            $nameTH                  = $request->name_th;
            $nameEN                  = $request->name_en;
            $employeeID              = $request->employees_id;
            $workStartDate           = $request->work_start_date;
            $dateOfBirth             = $request->date_of_birth;
            $telephone               = $request->telephone;
            $personalEmail           = $request->personal_email;
            $officialEmail           = $request->official_email;
            $picture                 = $request->picture;
            $emergencyContact        = $request->emergency_contact;
            $isGraduated            = $request->is_graduated;
            $greaduatedYear         = $request->graduated_year;
            $educationLevel         = $request->education_level;
            $universityID           = $request->university_id;
            $universityName         = $request->University_name;
            $major                  = $request->major;
            $schoolOf               = $request->school_of;
            $graduatedGPAX          = $request->graduated_gpax;
            $currentGPAX            = $request->current_gpax;
            $resumeDocs             = $request->resume_docs;
            $jobDescriptionDocs     = $request->job_desc_docs;
            $hrDocs                 = $request->hr_docs;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($accountID)];

            $options = ["limit" => 1, "projection" => ["_id" => 0, "account_id" => ['$toString' => '$_id']]];
            $chkID      = $this->db->selectCollection("Accounts")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "account dose not exist", "data" => []], 500);
            //! check data

            // return response()->json($queryAccountID->AccountID);

            $update = [
                // "team_id"                    => $this->MongoDBObjectId($teamID),
                "team_id"                    => $this->MongoDBObjectId($teamID),
                "position_id"                => $this->MongoDBObjectId($positionID),
                "name_th"                    => $nameTH,
                "name_en"                    => $nameEN,
                "employees_id"               => $employeeID,
                "work_start_date"            => $workStartDate,
                "date_of_birth"              => $dateOfBirth,
                "telephone"                  => $telephone,
                "personal_email"             => $personalEmail,
                "official_email"             => $officialEmail,
                "emergency_contact"          => $emergencyContact,
                "is_graduated"               => (bool)$isGraduated,
                "graduated_year"             => $greaduatedYear,
                "education_level"            => $educationLevel,
                "university_id"              => (int)$universityID,
                "University_name"            => $universityName,
                "major"                      => $major,
                "school_of"                  => $schoolOf,
                "graduated_gpax"             => $graduatedGPAX,
                "current_gpax"               => $currentGPAX,
                // "resume_docs"                => $resumeDocs,
                // "job_desc_docs"              => $jobDescriptionDocs,
                // "hr_docs"                    => $hrDocs,
                "updated_at"                 => $timestamp,
            ];

            $result = $this->db->selectCollection("Accounts")->updateOne($filter, ['$set' => $update]);


            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ]);

            return response()->json([
                "status" => "success",
                "message" => "Edit your accounts successfully !!",
                "data" => [$result]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ]);
        }
    }

    //* [DELETE] /account/delete-accounts
    public function deleteAccounts(Request $request)
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
                'account_id' => 'nullable | string',
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

            $AccountID = $request->account_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($AccountID)];

            $options = ["limit" => 1, "projection" => ["_id" => 0, "account_id" => ['$toString' => '$_id']]];
            $chkID      = $this->db->selectCollection("Accounts")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "account dose not exist", "data" => []], 500);
            //! check data

            $result = $this->db->selectCollection("Accounts")->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data deletion",
                    "data" => []
                ]);

            return response()->json([
                "status" => "success",
                "message" => "ํDelete account successfully !!",
                "data" => [$result]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => [],
            ]);
        }
    }

    //* [GET] /account/get-list-position
    public function getListPosition(Request $request)
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


            $pipeline = [['$project' => ['_id' => 0, 'role_id' => ['$toString' => '$_id'], 'short_name' => 1, 'name' => 1, 'desc' => 1]]];

            $result = $this->db->selectCollection("RoleResponsibility")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            // return response()->json([$data]);

            return response()->json([
                "status" => "success",
                "message" => "Get all list of role responsibility successfully",
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }

    //* [GET] /account/get-user
    public function getUserInSystem(Request $request)
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


            $pipeline = [['$project' => ['_id' => 0, 'user_id' => ['$toString' => '$_id'], 'name' => 1, 'email' => 1]]];

            $result = $this->db->selectCollection("Users")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all users in system successfully",
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }

    //* [GET] /account/list-position-id
    public function listPositionID(Request $request)
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


            $pipeline = [['$project' => ['_id' => 0, 'position_id' => ['$toString' => '$_id'], 'position_name' => '$Position', 'position_description' => '$PositionDescription']]];

            $result = $this->db->selectCollection("Positions")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all Positions id in system successfully",
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }

    //* [GET] /account/list-team-id
    public function listTeamID(Request $request)
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


            $pipeline = [['$project' => ['_id' => 0, 'team_id' => ['$toString' => '$_id'], 'team_description' => '$TeamDescription', 'team' => '$Team']]];

            $result = $this->db->selectCollection("Teams")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all Teams id in system successfully",
                "data" => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => "error",
                "message" => $e->getMessage(),
                "data" => []
            ]);
        }
    }


    //! [POST] /account/upload-document
    public function uploadDocument(Request $request)
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
                "account_id"        => "nullable | string ",
                "resume_docs"       => "nullable | string ",
                "job_desc_docs"     => "nullable | string ",
                "hr_docs"           => "nullable | string "
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

            $accountID = $request->account_id;

            $documentResume = $request->resume_docs;
            $documentJD = $request->job_desc_docs;
            $documentHR = $request->hr_docs;

            //! check data
            $filter = ['_id' => $this->MongoDBObjectId($accountID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "account_id" => ['$toString' => '$_id'],
                    "picture" => 1,
                    "resume_docs" => 1,
                    "job_desc_docs" => 1,
                    "hr_docs" => 1,
                ]
            ];

            $result = $this->db->selectCollection("Accounts")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "account id not found",
                    "data" => [],
                ], 400);
            //! check data

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($accountID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1]
            ];

            $userDocVersion = $this->db->selectCollection("Accounts")->aggregate($pipline);
            $dataUserDocument = array();
            foreach ($userDocVersion as $doc) \array_push($dataUserDocument, $doc);

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $path = getcwd() . "\\..\\documents\\Accounts\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/documents/Accounts/"; //server
            $fileName = $accountID . "_" . $timestamp . ".pdf";

            //? Resume
            if ($documentResume !== null) {
                if (str_starts_with($documentResume, 'data:application/pdf;base64,')) {
                    //save file to server
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentResume)));
                    $resumeDocs = $pathUsed . $fileName;
                }
            } else {
                $resumeDocs = $data[0]->resume_docs;
            }

            //? Job Description
            if ($documentJD !== null) {
                if (str_starts_with($documentJD, 'data:application/pdf;base64,')) {
                    //save file to server
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentJD)));
                    $jobDocs = $pathUsed . $fileName;
                }
            } else {
                $jobDocs = $data[0]->job_desc_docs;
            }

            //? HR Docs
            if ($documentHR !== null) {
                if (str_starts_with($documentHR, 'data:application/pdf;base64,')) {
                    //save file to server
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:application/\w+;base64,#i', '', $documentHR)));
                    $hrDocs = $pathUsed . $fileName;
                }
            } else {
                $hrDocs = $data[0]->hr_docs;
            }

            $upload = $this->db->selectCollection("Accounts")->updateOne($filter, [
                '$set' => [
                    "resume_docs"   => $resumeDocs,
                    "job_desc_docs" => $jobDocs,
                    "hr_docs"       => $hrDocs,
                    "updated_at"    => $timestamp,
                ]
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
    //! [POST] /account/edit-document
    public function editDocument(Request $request)
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
                "account_id"        => "nullable | string ",
                "resume_docs"       => "nullable | string ",
                "job_desc_docs"     => "nullable | string ",
                "hr_docs"           => "nullable | string "
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

            $accountID = $request->account_id;
            $documentResume = $request->resume_docs;

            $documentJD = $request->job_desc_docs;
            $documentHR = $request->hr_docs;

            // return response()->json($documentResume);

            //! check data
            $filter = ['_id' => $this->MongoDBObjectId($accountID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "account_id" => ['$toString' => '$_id'],
                    "picture" => 1,
                    "resume_docs" => 1,
                    "job_desc_docs" => 1,
                    "hr_docs" => 1,
                ]
            ];

            $result = $this->db->selectCollection("Accounts")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "account id not found",
                    "data" => [],
                ], 400);
            //! check data

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($accountID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1]
            ];

            $userDocVersion = $this->db->selectCollection("Accounts")->aggregate($pipline);
            $dataUserDocument = array();
            foreach ($userDocVersion as $doc) \array_push($dataUserDocument, $doc);

            $filter = ['_id' => $this->MongoDBObjectId($accountID)];

            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            // $path = getcwd()."\\..\\documents\\Accounts\\";
            // if(!is_dir($path)) mkdir($path,0777,true);
            // // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            // $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/documents/Accounts/"; //server
            // $fileName = $accountID."_".$timestamp.".pdf";

            //? Resume
            if ($documentResume === "REMOVE") {
                $resumeDocs = null;
            } else {
                $resumeDocs = $data[0]->resume_docs;
            }

            //? Job Description
            if ($documentJD === "REMOVE") {
                $jobDocs = null;
            } else {
                $jobDocs = $data[0]->job_desc_docs;
            }

            //? HR Docs
            if ($documentHR === "REMOVE") {
                $hrDocs = null;
            } else {
                $hrDocs = $data[0]->hr_docs;
            }

            $upload = $this->db->selectCollection("Accounts")->updateOne($filter, [
                '$set' => [
                    "resume_docs"   => $resumeDocs,
                    "job_desc_docs" => $jobDocs,
                    "hr_docs"       => $hrDocs,
                    "updated_at"    => $timestamp,
                ]
            ]);

            return response()->json([
                "status" => "success",
                "message" => "Update document successfully",
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

    //! [POST] /account/upload-picture
    public function uploadPicture(Request $request)
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
                "account_id"        => "nullable | string ",
                "picture"       => "nullable | string ",

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

            $accountID = $request->account_id;
            $picture = $request->picture;


            //! check data
            $filter = ['_id' => $this->MongoDBObjectId($accountID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "account_id" => ['$toString' => '$_id'],
                    "picture" => 1,
                    "resume_docs" => 1,
                    "job_desc_docs" => 1,
                    "hr_docs" => 1,
                ]
            ];

            $result = $this->db->selectCollection("Accounts")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "account id not found",
                    "data" => [],
                ], 400);
            //! check data

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($accountID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1]
            ];

            $userDocVersion = $this->db->selectCollection("Accounts")->aggregate($pipline);
            $dataUserDocument = array();
            foreach ($userDocVersion as $doc) \array_push($dataUserDocument, $doc);

            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $path = getcwd() . "\\..\\images\\Accounts\\";

            // return response()->json($path);

            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            // $pathUsed = 'http://10.1.8.235/dev/iPMS/iPMS-v5/ipms-v5-laravel/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/Accounts/"; //server

            $exp = explode("data:image/", $picture);
            $exp2 = explode(";", $exp[1]);

            //? Picture

            if (str_starts_with($picture, 'data:image/' . $exp2[0] . ';base64,')) {

                $folderPath = $path  . "\\";
                $fileName = $accountID . "_" . $timestamp . "." . $exp2[0];
                if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $picture)));
                $picture = $pathUsed . $fileName;

                $upload = $this->db->selectCollection("Accounts")->updateOne($filter, [
                    '$set' => [
                        "picture"       => $picture,
                        "updated_at"    => $timestamp,
                    ]
                ]);
            }


            return response()->json([
                "status" => "success",
                "message" => "Upload picture successfully",
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

    //! [PUT] /account/edit-picture
    public function editPicture(Request $request)
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
                "account_id"    => "nullable | string ",
                "picture"       => "nullable | string "
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $accountID = $request->account_id;
            $picture = $request->picture;

            //! check data
            $filter = ['_id' => $this->MongoDBObjectId($accountID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "account_id" => ['$toString' => '$_id'],
                    "picture" => 1,
                    "resume_docs" => 1,
                    "job_desc_docs" => 1,
                    "hr_docs" => 1,
                ]
            ];

            $result = $this->db->selectCollection("Accounts")->find($filter, $options);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            if (\count($data) == 0)
                return response()->json([
                    "status" =>  "error",
                    "message" => "account id not found",
                    "data" => [],
                ], 400);
            //! check data

            $copyData = $this->db->selectCollection("Accounts")->find(["_id" => $this->MongoDBObjectId($accountID)]);
            $dataCopy = array();
            foreach ($copyData as $doc) \array_push($dataCopy, $doc);


            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $path = getcwd() . "\\..\\images\\Accounts\\";
            if (!is_dir($path)) mkdir($path, 0777, true);
            // $pathUsed = 'http://10.1.9.77/Project/iPMS-ISO/documents/'.$projectID.'/'; // local
            $pathUsed = "https://snc-services.sncformer.com/dev/iPMSISO/images/Accounts/"; //server
            $fileName = $accountID . "_" . $timestamp . ".jpeg";

            if ($picture !== null) {
                if (str_starts_with($picture, 'data:image/jpeg;base64,')) {
                    // delete existing file
                    if (is_dir($path)) {
                        $files = scandir($path);
                        $files = array_diff($files, array('.', '..'));
                    }
                    //save file to server
                    $folderPath = $path  . "\\";
                    if (!is_dir($folderPath)) mkdir($folderPath, 0777, true);
                    file_put_contents($folderPath . $fileName, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $picture)));
                    $picture = $pathUsed . $fileName;
                }
            } else {
                $picture = null;
            }

            $update = $this->db->selectCollection("Accounts")->updateOne(
                ["_id" => $this->MongoDBObjectId($accountID)],
                ['$set' => [
                    "picture" => $picture,
                    "updated_at" => $timestamp
                ]]
            );

            return response()->json([
                "status" => "success",
                "message" => "updated successfully",
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
}
