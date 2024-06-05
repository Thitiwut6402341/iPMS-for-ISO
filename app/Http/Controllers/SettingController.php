<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Libraries\JWT\JWTUtils;
use Illuminate\Validation\Rule;
use App\Http\Libraries\Bcrypt;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class SettingController extends Controller
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

    //! ----------------------------------------------- Product type setting ---------------------------------------
    //* [POST] /setting/add-product-type
    public function addProductType(Request $request)
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
                'project_type'          => 'required | string | min:1 | max:100',
                'work_center_issue'     => 'required | array',
                'days'                  => 'nullable | int',
                'cost_estimation'       => 'required | array',
                'description'           => 'nullable | string | min:1 | max:100',
                'day_to_complete'       => 'required | int',
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

            $productType                  = $request->project_type;
            $workCenterIssue              = $request->work_center_issue;
            $days                         = $request->days;
            $costEstimation         = $request->cost_estimation;

            $description                  = $request->description;
            $dayComplete                  = $request->day_to_complete;

            $decoded = $jwt->decoded;
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);


            $document = [
                "project_type"                 => $productType,
                "work_center_issue"            => $workCenterIssue,
                "description"                  => $description,
                "cost_estimation"           => $costEstimation,

                "day_to_complete"              => $dayComplete,
                "creator_id"                    => $this->MongoDBObjectId($decoded->creater_by),
                "created_at"                   => $timestamp,
                // "updated_at"                   => $timestamp,
            ];


            $result = $this->db->selectCollection("ProjectTypeSetting")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Add Product Type failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add Product Type successfully",
                "data" => []
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /setting/edit-product-type
    public function editProductType(Request $request)
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
                'project_type_id'          => 'required | string | min:1 | max:100',
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

            $productTypeID                = $request->project_type_id;
            $productTypeName              = $request->project_type;
            $workCenterIssue              = $request->work_center_issue;
            $description                  = $request->description;
            $dayComplete                  = $request->day_to_complete;
            $costEstimation               = $request->cost_estimation;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ['_id' => $this->MongoDBObjectId($productTypeID)];
            $options = ['limit' => 1, 'projection' => [
                "_id" => 0, "project_type_id" => ['$toString' => '$_id'],
                "project_type" => 1, "work_center_issue" => 1, "cost_estimation" => 1
            ]];

            $chkProjectTypeID = $this->db->selectCollection("ProjectTypeSetting")->find($filter, $options);
            $dataChk = array();
            foreach ($chkProjectTypeID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Product type id not found", "data" => []], 500);

            // return response()->json($chkProjectTypeID);

            $update = [
                "project_type"                 => $productTypeName,
                "work_center_issue"            => $workCenterIssue,
                "description"                  => $description,
                "day_to_complete"              => $dayComplete,
                "cost_estimation"              => $costEstimation,
                "updated_at"                   => $timestamp,
            ];


            $result = $this->db->selectCollection("ProjectTypeSetting")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Edit product Type failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "edit product Type successfully",
                "data" => []
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [DELETE] /setting/delete-product-type
    public function deleteProductType(Request $request)
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
                'project_type_id'          => 'required | string | min:1 | max:100',
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

            $productTypeID  = $request->project_type_id;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');

            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($productTypeID)];

            $result = $this->db->selectCollection("ProjectTypeSetting")->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "delete product type failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "delete product type successfully",
                "data" => []
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [GET] /setting/get-product-type
    public function getProductType(Request $request)
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
                ['$project' =>
                [
                    'project_type_id' => ['$toString' => '$_id'], 'project_type' => 1, 'work_center_issue' => 1, "cost_estimation" => 1,
                    'description' => 1, 'day_to_complete' => 1, 'creator_id' => ['$toString' => '$creator_id'], '_id' => 0,
                ]]
            ];

            $result = $this->db->selectCollection("ProjectTypeSetting")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "you get all product type successfully",
                "data" => $data,
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //! ----------------------------------------------------- Label setting -------------------------------------------

    //* [POST] /setting/add-label
    public function addLabel(Request $request)
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
                'color'          => ["nullable", "string", "max:50"],
                'label_name'     => ["nullable", "string", "max:50"],
                'group'         =>  ["nullable", "string", "max:50"],
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

            $color               = $request->color;
            $labelName           = $request->label_name;
            $group               = $request->group;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $document = [
                "label_name"             => $labelName,
                "group"                  => $group,
                "color"                  => $color,
                "created_at"             => $timestamp,
            ];

            $result = $this->db->selectCollection("LabelSetting")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Add label failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add label successfully",
                "data" => [$document]
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /setting/edit-label
    public function editLabel(Request $request)
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
                'label_id'     => ["required", "string", "max:50"],
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

            $labelID             = $request->label_id;
            $color               = $request->color;
            $labelName           = $request->label_name;
            $group               = $request->group;

            $decoded = $jwt->decoded;
            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($labelID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "label_id" => ['$toString' => '$_id'],
                ]
            ];

            $chkLabelID = $this->db->selectCollection("LabelSetting")->find($filter, $options);

            $dataChk = array();
            foreach ($chkLabelID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "label ID not found", "data" => []], 500);

            $update = [
                "label_name"             => $labelName,
                "group"                  => $group,
                "color"                  => $color,
                "updated_at"             => $timestamp,
            ];

            $result = $this->db->selectCollection("LabelSetting")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "edit label failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "edit label successfully",
                "data" => [$update]
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

    //* [DELETE] /setting/delete-label
    public function deleteLabel(Request $request)
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
                'label_id'     => ["required", "string", "max:50"],
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

            $labelID             = $request->label_id;

            \date_default_timezone_set('Asia/Bangkok');
            $date = date('Y-m-d H:i:s');
            $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp() + 2.52e4) * 1000);

            $filter = ["_id" => $this->MongoDBObjectId($labelID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "label_id" => ['$toString' => '$_id'],
                ]
            ];

            $chkLabelID = $this->db->selectCollection("LabelSetting")->find($filter, $options);

            $dataChk = array();
            foreach ($chkLabelID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "label ID not found", "data" => []], 500);


            $result = $this->db->selectCollection("LabelSetting")->deleteOne($filter);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "delete label failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "delete label successfully",
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

    //* [GET] /setting/get-label
    public function getLabel(Request $request)
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
                ['$project' =>
                ['label_id' => ['$toString' => '$_id'], 'label_name' => 1, 'group' => 1, 'color' => 1, '_id' => 0,]]
            ];

            $result = $this->db->selectCollection("LabelSetting")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "you get all label successfully",
                "data" =>  $data,
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //! ----------------------------------------------------- Member setting --------------------------------------

    //* [POST] /setting/add-teamspace-member
    public function addMember(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $rules = [
                'teamspace_id'      => 'required | string',
                'teamspace_code'    => 'required | string',
                'members'           => 'required | array',
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

            $decoded = $jwt->decoded;

            $addMember      = $request->members;
            $teamSpaceCode  = $request->teamspace_code;
            $teamSpaceID    = $request->teamspace_id;

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID), "teamspace_code" => $teamSpaceCode];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "teamspace_name" => 1, "teamspace_code" => 1, "members" => 1, "invite_user" => 1]];

            $chkData = $this->db->selectCollection("Teamspaces")->findOne($filter, $options);

            if ($chkData == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID and Teamspace Code does not exist",
                    "data" => []
                ], 200);

            $dataMember = array();
            foreach ($addMember as $doc) \array_push($dataMember, ($doc));
            // foreach ($addMember as $doc) \array_push($dataMember, $this->MongoDBObjectId($doc));

            $queryMember  = $this->db->selectCollection("Teamspaces")->findOne($filter, $options)->members;

            foreach ((array)$dataMember as $doc) {
                for ($i = 0; $i < count($queryMember); $i++)
                    if ((array)$doc === (array)$queryMember[$i]) {
                        return response()->json([
                            "status" => "error",
                            "message" => "The member has been in teamspace already",
                            "data" => []
                        ], 500);
                    }
            }

            $dataPushMember = array_merge((array)$queryMember, (array)$dataMember);

            $document = [
                "members"       => $dataPushMember,
                // "members"       => $dataMember,
                "updated_at"    => $timestamp
            ];

            $result = $this->db->selectCollection("Teamspaces")->updateOne($filter, ['$set' => $document]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status"    => "error",
                    "message"   => "Add member failed",
                    "data"      => []
                ], 500);

            return response()->json([
                "status"    => "success",
                "message"   => "ํYou add new member in Teamspace successfully !!",
                "data"      => ["members" => $dataPushMember]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /setting/delete-teamspace-member
    public function deleteMember(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $rules = [
                'teamspace_id'      => 'required | string',
                'teamspace_code'    => 'required | string',
                'members'           => 'required | array',
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

            $decoded = $jwt->decoded;

            $editMember         = $request->members;
            $teamSpaceCode      = $request->teamspace_code;
            $teamSpaceID        = $request->teamspace_id;

            $filter1 = ["_id" => $this->MongoDBObjectId($teamSpaceID)];
            $filter2 = ["teamspace_code" => $teamSpaceCode];

            $option = ["limit" => 1, "projection" => ["_id" => 0, "teamspace_name" => 1, "teamspace_code" => 1, "members" => 1, "invite_user" => 1]];

            $chkDataID = $this->db->selectCollection("Teamspaces")->findOne($filter1, $option);
            $chkDataTeamSpaceCode = $this->db->selectCollection("Teamspaces")->findOne($filter2, $option);

            if ($chkDataTeamSpaceCode == null and $chkDataID == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID and Teamspace Code does not exist",
                    "data" => []
                ], 200);

            if ($chkDataID == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID does not exist",
                    "data" => []
                ], 200);

            if ($chkDataTeamSpaceCode == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace Code does not exist",
                    "data" => []
                ], 200);

            $dataMember = array();
            // foreach ($editMember as $doc) \array_push($dataMember, $this->MongoDBObjectId($doc));
            foreach ($editMember as $doc) \array_push($dataMember, ($doc));

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID), "teamspace_code" => $teamSpaceCode];
            $options = [
                "limit" => 1, "projection" => ["_id" => 0, "members" => 1, "invite_user" => 1,]
            ];

            $queryMember  = $this->db->selectCollection("Teamspaces")->findOne($filter, $options)->members;

            $dataDiff = array_diff((array)$queryMember, (array)$dataMember);

            $newMembers = array_values($dataDiff);

            $update = [
                "members"       => $newMembers,
                "updated_at"    => $timestamp
            ];

            $result = $this->db->selectCollection("Teamspaces")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Delete member failed",
                    "data" => [$result]
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou delete member in Teamspace successfully !!",
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

    //* [POST] /setting/add-invite-user
    public function addInviteUser(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $rules = [
                'teamspace_id'      => 'required | string',
                'teamspace_code'    => 'required | string',
                'invite_user'       => 'required | array',
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

            $decoded = $jwt->decoded;

            $addInvite      = $request->invite_user;
            $teamSpaceCode  = $request->teamspace_code;
            $teamSpaceID    = $request->teamspace_id;

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID), "teamspace_code" => $teamSpaceCode];
            $option = ["limit" => 1, "projection" => ["_id" => 0, "teamspace_name" => 1, "teamspace_code" => 1, "members" => 1, "invite_user" => 1]];

            $chkData = $this->db->selectCollection("Teamspaces")->findOne($filter, $option);

            if ($chkData == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID and Teamspace Code does not exist",
                    "data" => []
                ], 200);

            $dataInvite = array();
            foreach ($addInvite as $doc) \array_push($dataInvite, ($doc));

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID), "teamspace_code" => $teamSpaceCode];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "members" => 1, "invite_user" => 1,]];

            $queryInvite  = $this->db->selectCollection("Teamspaces")->findOne($filter, $options)->invite_user;

            foreach ((array)$dataInvite as $doc) {
                for ($i = 0; $i < count($queryInvite); $i++)
                    if ((array)$doc === (array)$queryInvite[$i]) {
                        return response()->json([
                            "status" => "error",
                            "message" => "Invite user already",
                            "data" => []
                        ], 500);
                    }
            }

            $dataPushInvite = array_merge((array)$queryInvite, (array)$dataInvite);
            $document = [
                "invite_user"       => $dataPushInvite,
                // "invite_user"       => $dataInvite,
                "updated_at"        => $timestamp
            ];

            $result = $this->db->selectCollection("Teamspaces")->updateOne($filter, ['$set' => $document]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Invite member failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou invite new member in Teamspace successfully !!",
                "data" => ["invite_user" => $dataPushInvite]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    //* [PUT] /setting/delete-invite-user
    public function deleteInviteUser(Request $request)
    {
        try {
            $header = $request->header('Authorization');
            $jwt = $this->jwtUtils->verifyToken($header);
            if (!$jwt->state) return response()->json([
                "status" => "error",
                "message" => "Unauthorized",
                "data" => [],
            ], 401);

            $rules = [
                'teamspace_id'      => 'required | string',
                'teamspace_code'    => 'required | string',
                'invite_user'       => 'required | array',
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

            $decoded = $jwt->decoded;

            $editInvite        = $request->invite_user;
            $teamSpaceCode      = $request->teamspace_code;
            $teamSpaceID        = $request->teamspace_id;

            $filter1 = ["_id" => $this->MongoDBObjectId($teamSpaceID)];
            $filter2 = ["teamspace_code" => $teamSpaceCode];

            $option = ["limit" => 1, "projection" => ["_id" => 0, "teamspace_name" => 1, "teamspace_code" => 1, "members" => 1, "invite_user" => 1]];

            $chkDataID = $this->db->selectCollection("Teamspaces")->findOne($filter1, $option);
            $chkDataTeamSpaceCode = $this->db->selectCollection("Teamspaces")->findOne($filter2, $option);

            if ($chkDataTeamSpaceCode == null and $chkDataID == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID and Teamspace Code does not exist",
                    "data" => []
                ], 200);

            if ($chkDataID == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace ID does not exist",
                    "data" => []
                ], 200);

            if ($chkDataTeamSpaceCode == null)
                return response()->json([
                    "status" => "error",
                    "message" => "Teamspace Code does not exist",
                    "data" => []
                ], 200);

            $dataInvite = array();
            // foreach ($editInvite as $doc) \array_push($dataMember, $this->MongoDBObjectId($doc));
            foreach ($editInvite as $doc) \array_push($dataInvite, ($doc));

            $filter = ["_id" => $this->MongoDBObjectId($teamSpaceID), "teamspace_code" => $teamSpaceCode];
            $options = [
                "limit" => 1, "projection" => ["_id" => 0, "members" => 1, "invite_user" => 1,]
            ];

            $queryInvite  = $this->db->selectCollection("Teamspaces")->findOne($filter, $options)->invite_user;


            $dataDiff = array_diff((array)$queryInvite, (array)$dataInvite);

            $newInvite = array_values($dataDiff);

            $update = [
                "invite_user"       => $newInvite,
                "updated_at"        => $timestamp
            ];

            $result = $this->db->selectCollection("Teamspaces")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Delete invited failed",
                    "data" => [$result]
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou delete invite in Teamspace successfully !!",
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

    //!----------------------------------------------- Status setting ----------------------------------------------------

    //* [POST] /setting/add-new-status-group
    public function addNewStatusGroup(Request $request)
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
                'status_group'       => ['required',  'string'],            // Rule::in(["Backlog","Unstarted" , "Started" , "Completed","Canceled"])
                'status'             => 'required | array',
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

            $statusGroup     = $request->status_group;
            $Status          = $request->status;

            $document = array(
                "status_group"              => $statusGroup,
                "status"                    => $Status,
                "creator_id"                 => $this->MongoDBObjectId($decoded->creater_by),
                "created_at"                => $timestamp,
            );

            $result = $this->db->selectCollection("StatusIssue")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data to insert",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add new stats successfully !!",
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

    //* [GET] /setting/get-status-group
    public function getStatusGroup(Request $request)
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

            $pipeline = [['$project' => ['_id' => 0, 'status_group_id' => ['$toString' => '$_id'], 'status_group' => 1, 'status' => 1]]];


            $result = $this->db->selectCollection("StatusIssue")->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all stats successfully !!",
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

    //* [POST] /setting/new-status
    public function newStatus(Request $request)
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
                'status_group_id'     => ['required',  'string'],            // Rule::in(["Backlog","Unstarted" , "Started" , "Completed","Canceled"])
                'new_status'          => 'required | array',
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

            $statusGroupID      = $request->status_group_id;
            $newStatus          = $request->new_status;

            $dataNewStatus = [];
            foreach ($newStatus as $info) \array_push($dataNewStatus, $info);

            //! check data

            $filter = ["_id" => $this->MongoDBObjectId($statusGroupID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "status_group_id" => ['$toString' => '$_id'], "status_group" => 1, "status" => 1,]];
            $chkID      = $this->db->selectCollection("StatusIssue")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "Status group id not found", "data" => []], 500);

            //! check data
            $oldStatus  = $dataChkID[0]->status;
            // return response()->json($oldStatus);


            $filter = ["_id" => $this->MongoDBObjectId($request->status_group_id)];

            $dataPush = array_merge((array)$oldStatus, (array)$dataNewStatus);

            $update = [
                "status"            => $dataPush,
                "updated_at"        => $timestamp,
            ];

            // return response()->json($update);

            $result = $this->db->selectCollection('StatusIssue')->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add new stats successfully !!",
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

    //* [POST] /setting/delete-status
    public function deleteStatus(Request $request)
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
                'status_group_id'       => ['required',  'string'],            // Rule::in(["Backlog","Unstarted" , "Started" , "Completed","Canceled"])
                'new_status'                => 'required | array',
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

            $statusGroupID      = $request->status_group_id;
            $newStatus          = $request->new_status;

            $dataNewStatus = [];
            foreach ($newStatus as $info) \array_push($dataNewStatus, $info);

            //! check data

            $filter = ["_id" => $this->MongoDBObjectId($statusGroupID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "status_group_id" => ['$toString' => '$_id'], "status_group" => 1, "status" => 1,]];
            $chkID      = $this->db->selectCollection("StatusIssue")->find($filter, $options);

            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);

            if (\count($dataChkID) == 0)
                return response()->json(["status" => "error", "message" => "Status group id not found", "data" => []], 500);

            //! check data
            $oldStatus  = $dataChkID[0]->status;

            $dataDiff = array_diff((array)$oldStatus, (array)$dataNewStatus);

            $newDataStatus = array_values($dataDiff);

            $filter = ["_id" => $this->MongoDBObjectId($request->status_group_id)];

            $update = [
                "status"            => $newDataStatus,
                "updated_at"        => $timestamp,
            ];

            $result = $this->db->selectCollection('StatusIssue')->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
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
}
