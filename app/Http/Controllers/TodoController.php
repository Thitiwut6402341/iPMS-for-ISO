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

class TodoController extends Controller
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

    //* [GET] /todo/get-todo
    public function getToDo(Request $request)
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

            $pipeline1 =  [
                // ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'projectTypeDesc']],
                // ['$project' => ['_id' => 0, 'todo_id' => ['$toString' => '$_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'create_by' => ['$toString' => '$create_by'], 'start_date' => 1, 'end_date' => 1, 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'create_by' => ['$toString' => '$create_by'], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'project_type' => ['$arrayElemAt' => ['$projectTypeDesc.project_type', 0]], 'cost_estimation' => ['$arrayElemAt' => ['$projectTypeDesc.cost_estimation', 0]]]],
                // ['$project' => ['_id' => 0, 'todo_id' => 1, 'project_type_id' => 1, 'teamspace_id' => 1, 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'create_by' => 1, 'start_date' => 1, 'end_date' => 1, 'day_remain' => 1, 'create_by' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_type' => 1, 'total_estimation' => ['$sum' => [['$arrayElemAt' => ['$cost_estimation.raw_materials', 0]], ['$arrayElemAt' => ['$cost_estimation.direct_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.overhead_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.gross_profit', 0]]]], 'is_aproved' => 1]]

                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'projectTypeDesc', 'pipeline' => [['$project' => ['_id' => 0, 'project_type' => '$project_type']]]]],
                ['$project' => ['_id' => 0, 'todo_id' => ['$toString' => '$_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'create_by' => ['$toString' => '$create_by'], 'start_date' => 1, 'end_date' => 1, 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'create_by' => ['$toString' => '$create_by'], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'project_type' => ['$arrayElemAt' => ['$projectTypeDesc.project_type', 0]], 'todo_prices' => 1]]
            ];

            $result1 = $this->db->selectCollection('Todo')->aggregate($pipeline1);

            $data = array();
            foreach ($result1 as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all porject issue successfully !!",
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

    //* [POST] /todo/add-todo
    public function addToDoList(Request $request)
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
                "project_type_id"        => 'required | string | min:1 | max:50',
                'teamspace_id'           => 'required | string | min:1 | max:100',
                "issue_name"             => 'required | string | min:1 | max:255',
                "description"            => 'required | string ',
                "difficulty_level"       => ['required', 'string', Rule::in(["NEW_COMER", "BEGINNER", "INTERMEDIATE", "ADVANCED", "EXPERT"])],
                "status_group"           => ['required', 'string', Rule::in(["backlog", "unstarted", "started", "completed", "canceled"])],
                "status"                 => ['required', 'string', 'min:1', 'max:200'],
                "assigned"               => 'required | array', //! need to recorde in Object ID
                "priority"               => ['required', 'string', Rule::in(["high", "medium", "low", "urgent", "none"])],
                "labels"                 => 'nullable | array',
                "tags"                   => 'nullable | string | min:1 | max:100',
                "require_check_by"       => 'nullable | string | min:1 | max:100',
                "link"                   => 'nullable | array',
                "start_date"             => 'nullable | string | min:1 | max:20',
                "end_date"               => 'nullable | string | min:1 | max:20',
                "todo_prices"            => 'nullable | numeric',
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

            //! Check data
            $project_type_id = $request->project_type_id;
            $filter = ["_id" => $this->MongoDBObjectId($project_type_id)];
            $options = ["projection" => ["_id" => 0, "project_type_id" => ['$toString' => '$_id']]];

            $chkProjectID = $this->db->selectCollection("ProjectTypeSetting")->find($filter, $options);
            $dataChk = array();

            foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);
            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Project Type not found", "data" => []], 500);

            $filter2 = ["_id" => $this->MongoDBObjectId($request->teamspace_id)];
            $opions2 = ["projection" => ["_id" => 0, "teamspace_id" => ['$toString' => '$_id'], "teamspace_code" => 1]];

            $chkTeamspaceID = $this->db->selectCollection("Teamspaces")->find($filter2, $opions2);
            $dataChk2 = array();
            foreach ($chkTeamspaceID as $info) \array_push($dataChk2, $info);
            if (\count($dataChk2) == 0)
                return response()->json(["status" => "error", "message" => "Teamspace ID not found", "data" => []], 500);
            //! Check data

            $projectTypeID     = $request->project_type_id;
            $teamspaceID        = $request->teamspace_id;
            $issueName          = $request->issue_name;
            $description        = $request->description;
            $difficultyLevel    = $request->difficulty_level;
            $status             = $request->status;
            $statusGroup        = $request->status_group;
            $assigned           = $request->assigned;
            $priority           = $request->priority;
            $labels             = $request->labels;
            $tags               = $request->tags;
            $requireCheck       = $request->require_check_by;
            $startDate          = $request->start_date;
            $endDate            = $request->end_date;
            $link               = $request->link;
            $todoPrices         = $request->todo_prices;

            $dataAssigned = [];
            foreach ($assigned as $doc) \array_push($dataAssigned, $this->MongoDBObjectId($doc));

            $dataListLink = [];
            if ($link != null) {
                $dataLink = [];
                foreach ($link as $j) \array_push($dataLink, $j);
                for ($i = 0; $i < count($link); $i++) {
                    $list = [
                        "title"      => $dataLink[$i]["title"],
                        "url"      => $dataLink[$i]["url"],
                    ];
                    array_push($dataListLink, $list);
                };
            } else {
                $dataListLink = null;
            }

            if ($requireCheck != null) {
                $requireCheck = $this->MongoDBObjectId($requireCheck);
            } else {
                $requireCheck = null;
            }

            $document = array(
                "project_type_id"           => $this->MongoDBObjectId($projectTypeID),
                "teamspace_id"              => $this->MongoDBObjectId($teamspaceID),
                "issue_name"                => $issueName,
                "description"               => $description,
                "difficulty_level"          => $difficultyLevel,
                "status_group"              => $statusGroup,
                "status"                    => $status,
                "assigned"                  => $dataAssigned,
                "priority"                  => $priority,
                "labels"                    => $labels,
                "tags"                      => $tags,
                "require_check_by"          => $requireCheck,
                "link"                      => $dataListLink,
                "create_by"                 => $this->MongoDBObjectId($decoded->creater_by),
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                "created_at"                => $timestamp,
                "is_approved"               => null,
                "todo_prices"               => $todoPrices,
            );

            $result = $this->db->selectCollection("Todo")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Add To do successfully !!",
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

    //* [PUT] /todo/edit-todo
    public function editToDo(Request $request)
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

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'todo_id'         => 'required | string | min:1 | max:255',
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

            $toDoID     = $request->todo_id;

            $filter = ["_id" => $this->MongoDBObjectId($toDoID)]; //, "TeamCode" => $decoded->TeamCode
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "todo_id"      => ['$toString' => '$_id'],
                ]
            ];

            $chkProjectIssueID = $this->db->selectCollection("Todo")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "Todo not found 5555", "data" => []], 500);

            $issueName          = $request->issue_name;
            $description        = $request->description;
            $difficultyLevel    = $request->difficulty_level;
            $status             = $request->status;
            $statusGroup        = $request->status_group;
            $assigned           = $request->assigned;
            $priority           = $request->priority;
            $labels             = $request->labels;
            $tags               = $request->tags;
            $requireCheck       = $request->require_check_by;
            $startDate          = $request->start_date;
            $endDate            = $request->end_date;
            $link               = $request->link;
            $todoPrices         = $request->todo_prices;

            $dataAssigned = [];
            foreach ($assigned as $doc) \array_push($dataAssigned, $this->MongoDBObjectId($doc));

            $queryOldData = $this->db->selectCollection("Todo")->findOne(["_id" => $this->MongoDBObjectId($request->todo_id)], ["projection" => ["_id" => 0, "link" => 1]]);

            $linkPrevious = [];
            if (!is_null($queryOldData->link)) {
                $linkPrevious = $queryOldData->link;
            } else {
                $linkPrevious = null;
            }

            ##Link
            $dataListLink = [];
            if (!is_null($link)) {
                $dataLink = [];
                foreach ($link as $j) \array_push($dataLink, $j);
                for ($i = 0; $i < count($link); $i++) {
                    $list = [
                        "title"      => $dataLink[$i]["title"],
                        "url"      => $dataLink[$i]["url"],
                    ];
                    array_push($dataListLink, $list);
                };
            } else {
                $dataListLink = null;
            }

            $dataListLink = array_merge((array)$dataListLink, (array)$linkPrevious);

            if ($requireCheck != null) {
                $requireCheck = $this->MongoDBObjectId($requireCheck);
            } else {
                $requireCheck = null;
            }

            $update = array(
                "issue_name"                => $issueName,
                "description"               => $description,
                "difficulty_level"          => $difficultyLevel,
                "status_group"              => $statusGroup,
                "status"                    => $status,
                "assigned"                  => $dataAssigned,
                "priority"                  => $priority,
                "labels"                    => $labels,
                "tags"                      => $tags,
                "require_check_by"          => $requireCheck,
                "link"                      => $dataListLink,
                "start_date"                => $startDate,
                "end_date"                  => $endDate,
                "updated_at"                => $timestamp,
                "todo_prices"               => $todoPrices,
            );

            $result = $this->db->selectCollection("Todo")->updateOne($filter, ['$set' => $update]);

            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data modification",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "ํYou edit todo successfully !!",
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

    //* [DELETE] /todo/delete-todo
    public function deleteToDo(Request $request)
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

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'todo_id'   => 'required | string |min:1|max:255',
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

            $toDoID     = $request->todo_id;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($toDoID)];
            $options = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0,
                    "todo_id" => ['$toString' => '$_id'],
                ]
            ];

            $chkProjectIssueID = $this->db->selectCollection("Todo")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0)
                return response()->json(["status" => "error", "message" => "ToDo ID not found", "data" => []], 500);
            //! check data

            $result = $this->db->selectCollection("Todo")->deleteOne($filter,);

            if ($result->getDeletedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "There has been no data deletion",
                    "data" => [],
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Delete todo successfully !!",
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

    // ********************************************* Status Tasks ************************************************************
    //? [PUT] /todo/change-status-todo
    public function changeStatusToDo(Request $request)
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
            $userID = $decoded->user_id;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector', 'user'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);
            $rules = [
                'todo_id'         => 'required | string |min:1|max:255',
                'status_group'       => 'required | string |min:1|max:255',
                'status'         => 'required | string |min:1|max:255',
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

            $toDoID     = $request->todo_id;
            $status_group   = $request->status_group;
            $status         = $request->status;

            //new ============================================================================================================
            # get assigned from database
            $filter = ["_id" => $this->MongoDBObjectId($toDoID)];
            $piplelineAssignee = [
                ['$match' => ['_id' => $this->MongoDBObjectId($toDoID)]],
                ['$project' => ['_id' => 0, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'item', 'in' => ['$toString' => '$$item']]], 'require_check_by' => ['$toString' => '$require_check_by']]]
            ];
            $resultAssignee = $this->db->selectCollection('Todo')->aggregate($piplelineAssignee);

            $dataAssignee = array();
            foreach ($resultAssignee as $doc) \array_push($dataAssignee, $doc);

            // return response()->json($dataAssignee[0]->assigned);
            // return response()->json($userID);

            #check user id in dataAssignee
            if (in_array($userID, (array)$dataAssignee[0]->assigned)) {
                if ($status == 'Done') {
                    if ($dataAssignee[0]->require_check_by != null) {
                        $update = [
                            "status_group"  => "started",
                            "status"        => "In Review",
                            "updated_at"    => $timestamp,
                        ];
                        $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);
                        return response()->json([
                            "status" => "success",
                            "message" => "Please waiting for inspector approval",
                            "data" => [$result]
                        ], 200);
                    } else {
                        $update = [
                            "status_group"  => $status_group,
                            "status"        => $status,
                            "is_approved"   => true,
                            "updated_at"    => $timestamp,
                        ];
                        $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);
                        return response()->json([
                            "status" => "success",
                            "message" => "Done",
                            "data" => [$result]
                        ], 200);
                    }
                } else if ($status_group == "canceled") {
                    $update = [
                        "status_group"  => $status_group,
                        "status"        => $status,
                        "is_approved"    => false,
                        "updated_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);
                    return response()->json([
                        "status" => "success",
                        "message" => "canceled",
                        "data" => [$result]
                    ], 200);
                } else {
                    $update = [
                        "status_group"  => $status_group,
                        "status"        => $status,
                        "updated_at"    => $timestamp,
                    ];
                    $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);
                    return response()->json([
                        "status" => "success",
                        "message" => "Edit status successfully !!",
                        "data" => [$result]
                    ], 200);
                }
            } else {
                return response()->json([
                    "status" => "error",
                    "message" => "You are not assigned with this task",
                    "data" => []
                ], 401);
            }
            //============================================================================================================
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [GET] /todo/get-waiting-review
    public function getWaitingReview(Request $request)
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
            $userID = $decoded->user_id;

            // if (!in_array($decoded->Role, ['owner', 'admin', 'inspector'])) return $this->response->setJSON(['state' => false, 'msg' => 'Access denied']);

            // ใช้แทน decode token ไปก่อน
            // $rules = [
            //     'require_check_by'         => 'required | string |min:1|max:255',

            // ];

            // $validators = Validator::make($request->all(), $rules);

            // if ($validators -> fails()) {
            //     return response()->json([
            //         "status" => "error",
            //         "message" => "Bad request",
            //         "data" => [
            //             [
            //                 "validator" => $validators -> errors()
            //             ]
            //         ]
            //     ], 400);
            // }

            // $requireCheckBy = $request -> require_check_by;

            $pipeline = [
                ['$match' => ["require_check_by" => $this->MongoDBObjectId($userID)]],
                ['$match' => ["status" => "In Review"]],
                ['$project' => ['_id' => 0, 'todo_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'iso_process' => 1, 'difficulty_level' => 1, 'sub_issue' => 1, 'status_group' => 1, 'status' => 1, 'parent_id' => ['$toString' => '$parent_id'], 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'comments' => ['$map' => ['input' => '$comments', 'as' => 'resp', 'in' => ['user_id' => ['$toString' => '$$resp.user_id'], 'comment' => '$$resp.comment', 'comment_at' => ['$dateToString' => ['date' => '$$resp.comment_at', 'format' => '%Y-%m-%d %H:%M:%S']]]]], 'start_date' => 1, 'end_date' => 1, 'create_by' => ['$toString' => '$create_by'], 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
            ];

            $result = $this->db->selectCollection('Todo')->aggregate($pipeline);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all todo successfully !!",
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

    //? [UPDATE] /todo/inspection-approval
    public function inpectionApproval(Request $request)
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
            $userID = $decoded->user_id;

            $rules = [
                'todo_id'      => 'required | string |min:1|max:255',
                "status_group"  => 'required | string |min:1|max:255',
                'status'        => 'required | string |min:1|max:255',
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

            $toDoID     = $request->todo_id;
            $status_group   = $request->status_group;
            $status         = $request->status;

            //! check data
            $filter = ["_id" => $this->MongoDBObjectId($toDoID)];
            $options = ["limit" => 1, "projection" => ["_id" => 0, "todo_id" => ['$toString' => '$_id'], "issue_name" => 1, "status_group" => 1, "status" => 1, "is_approved" => 1, "require_check_by" => ['$toString' => '$require_check_by'], "comments" => 1]];

            $chkProjectIssueID = $this->db->selectCollection("Todo")->find($filter, $options);

            $dataChk = array();
            foreach ($chkProjectIssueID as $doc) \array_push($dataChk, $doc);

            if (\count($dataChk) == 0) {
                return response()->json([
                    "status" => "error",
                    "message" => "ToDo id not found",
                    "data" => []
                ], 500);
            }
            //! check data

            if ($status_group == 'completed') {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    "is_approved"    => true,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Approved",
                    "data" => [$result]
                ], 200);
            } else if ($status_group == 'canceled') {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    "is_approved"    => false,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Rejected",
                    "data" => [$result]
                ], 200);
            } else {
                $update = [
                    "status_group"  => $status_group,
                    "status"        => $status,
                    "updated_at"    => $timestamp,
                ];

                $result = $this->db->selectCollection('Todo')->updateOne($filter, ['$set' => $update]);

                return response()->json([
                    "status" => "success",
                    "message" => "Edit todo successfully !!",
                    "data" => [$result]
                ], 200);
            }
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                "data"  => [],
            ], $statusCode);
        }
    }

    //? [GET] /todo/get-todo-by-user
    public function getToDoByUser(Request $request)
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

            ##main issue
            $pipeline1 =  [
                // ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'projectTypeDesc']],
                // ['$project' => ['_id' => 0, 'todo_id' => ['$toString' => '$_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => 1, 'link' => 1, 'create_by' => ['$toString' => '$create_by'], 'start_date' => 1, 'end_date' => 1, 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'create_by' => ['$toString' => '$create_by'], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'project_type' => ['$arrayElemAt' => ['$projectTypeDesc.project_type', 0]], 'cost_estimation' => ['$arrayElemAt' => ['$projectTypeDesc.cost_estimation', 0]]]],
                // ['$project' => ['_id' => 0, 'todo_id' => 1, 'project_type_id' => 1, 'teamspace_id' => 1, 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => 1, 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'create_by' => 1, 'start_date' => 1, 'end_date' => 1, 'day_remain' => 1, 'create_by' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_type' => 1, 'total_estimation' => ['$sum' => [['$arrayElemAt' => ['$cost_estimation.raw_materials', 0]], ['$arrayElemAt' => ['$cost_estimation.direct_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.overhead_cost', 0]], ['$arrayElemAt' => ['$cost_estimation.gross_profit', 0]]]], 'is_aproved' => 1]],
                // ['$unwind' => '$assigned'],
                // ['$group' => ['_id' => '$assigned', 'todo' => ['$push' => ['issue_name' => '$issue_name', 'description' => '$description', 'difficulty_level' => '$difficulty_level', 'status_group' => '$status_group', 'status' => '$status', 'priority' => '$priority', 'labels' => '$labels', 'tags' => '$tags', 'link' => '$link', 'start_date' => '$start_date', 'end_date' => '$end_date', 'issue_id' => 'issue_id', 'require_check_by' => '$require_check_by', 'create_by' => '$create_by', 'day_remain' => '$day_remain', 'created_at' => '$created_at', 'updated_at' => 'updated_at', 'project_id' => '$project_id', 'projecct_type_id' => '$projecct_type_id', 'project_type' => '$project_type', 'teamspace_id' => '$teamspace_id', 'total_estimation' => '$total_estimation']]]],
                // ['$project' => ['_id' => 0, 'user_id' => ['$toObjectId' => '$_id'], 'todo' => 1]],
                // ['$lookup' => ['from' => 'Users', 'localField' => 'user_id', 'foreignField' => '_id', 'as' => 'user']],
                // ['$project' => ['_id' => 0, 'todo' => 1, 'user_id' => ['$toString' => '$user_id'], 'name' => ['$arrayElemAt' => ['$user.name', 0]]]],

                ['$lookup' => ['from' => 'ProjectTypeSetting', 'localField' => 'project_type_id', 'foreignField' => '_id', 'as' => 'projectTypeDesc', 'pipeline' => [['$project' => ['_id' => 0, 'project_type' => '$project_type']]]]],
                ['$project' => ['_id' => 0, 'todo_id' => ['$toString' => '$_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'teamspace_id' => ['$toString' => '$teamspace_id'], 'issue_name' => 1, 'description' => 1, 'difficulty_level' => 1, 'status_group' => 1, 'status' => 1, 'assigned' => ['$map' => ['input' => '$assigned', 'as' => 'assignedItem', 'in' => ['$toString' => '$$assignedItem']]], 'priority' => 1, 'labels' => 1, 'tags' => 1, 'require_check_by' => ['$toString' => '$require_check_by'], 'link' => 1, 'create_by' => ['$toString' => '$create_by'], 'start_date' => 1, 'end_date' => 1, 'day_remain' => ['$dateDiff' => ['startDate' => ['$toDate' => '$start_date'], 'endDate' => ['$toDate' => '$end_date'], 'unit' => 'day']], 'create_by' => ['$toString' => '$create_by'], 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'project_type' => ['$arrayElemAt' => ['$projectTypeDesc.project_type', 0]], 'todo_prices' => 1]],
                ['$unwind' => '$assigned'],
                ['$group' => ['_id' => '$assigned', 'todo' => ['$push' => ['issue_name' => '$issue_name', 'description' => '$description', 'difficulty_level' => '$difficulty_level', 'status_group' => '$status_group', 'status' => '$status', 'priority' => '$priority', 'labels' => '$labels', 'tags' => '$tags', 'link' => '$link', 'start_date' => '$start_date', 'end_date' => '$end_date', 'issue_id' => 'issue_id', 'require_check_by' => '$require_check_by', 'create_by' => '$create_by', 'day_remain' => '$day_remain', 'created_at' => '$created_at', 'updated_at' => 'updated_at', 'project_id' => '$project_id', 'projecct_type_id' => '$projecct_type_id', 'project_type' => '$project_type', 'teamspace_id' => '$teamspace_id', 'todo_prices' => '$todo_prices']]]],
                ['$project' => ['_id' => 0, 'user_id' => ['$toObjectId' => '$_id'], 'todo' => 1]],
                ['$lookup' => ['from' => 'Users', 'localField' => 'user_id', 'foreignField' => '_id', 'as' => 'user']],
                ['$project' => ['_id' => 0, 'todo' => 1, 'user_id' => ['$toString' => '$user_id'], 'name' => ['$arrayElemAt' => ['$user.name', 0]]]]
            ];

            $result1 = $this->db->selectCollection('Todo')->aggregate($pipeline1);

            $array1 = array();
            foreach ($result1 as $doc) \array_push($array1, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all issue by user successfully !!",
                "data" => $array1
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
