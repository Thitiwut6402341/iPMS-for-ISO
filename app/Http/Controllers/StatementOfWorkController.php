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

class StatementOfWorkController extends Controller
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
    private function randomName(int $length = 24)
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

    //* [POST] /project/evidence-list
    public function evidencesList(Request $request)
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
                'project_id'             => 'required | string | min:1 | max:255',
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

            $projectID = $request->project_id;

            // $pipelineProject = [
            //     ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
            //     ['$sort' => ['created_at' => -1]], ['$limit' => 1],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$_id'], 'project_name' => 1]]
            // ];

            // $resultProjects = $this->db->selectCollection('Projects')->aggregate($pipelineProject);
            // $dataProjects = array();
            // foreach ($resultProjects as $doc) \array_push($dataProjects, $doc);

            // $projectName = $dataProjects[0]->project_name;


            $pipelineAccRecode = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]], ['$limit' => 1],
                ['$project' => ['_id' => 0, 'acceptance_record_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'ACCEPT_RECORD']]
            ];

            $resultAcc = $this->db->selectCollection('AcceptanceRecord')->aggregate($pipelineAccRecode);
            $dataAcc = array();
            foreach ($resultAcc as $doc) \array_push($dataAcc, $doc);

            if (count($dataAcc) == 0) {

                $acceptance_record_id = null;
            } else {
                $acceptance_record_id = $dataAcc[0]->acceptance_record_id;
            }


            $pipelineProjectPlan = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'project_plan_id' => ['$toString' => '$_id'], "version" => 1, 'verifitype_type' => "PROJECT_PLAN", 'project_id' => ['$toString' => '$project_id']]]
            ];

            $resultPP = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipelineProjectPlan);
            $dataPP = array();
            foreach ($resultPP as $doc) \array_push($dataPP, $doc);

            if (count($dataPP) == 0) {

                $project_plan_id = null;
            } else {
                $project_plan_id = $dataPP[0]->project_plan_id;
            }

            $pipelineProjectRepo = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]], ['$limit' => 1],
                ['$project' => ['_id' => 0, 'project_repo_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'project_repo' => 1, 'verifitype_type' => 'PROJECT_REPO']]
            ];

            $resultProjectRepo = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipelineProjectRepo);
            $dataProjectRepo = array();
            foreach ($resultProjectRepo as $doc) \array_push($dataProjectRepo, $doc);


            if (count($dataProjectRepo) == 0) {
                $project_repo_id = null;
            } else {
                $project_repo_id = $dataProjectRepo[0]->project_repo_id;
            }

            $pipelineProjectBackup = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]], ['$limit' => 1],
                ['$project' => ['_id' => 0, 'project_backup_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'project_backup' => 1, 'verifitype_type' => 'PROJECT_BACKUP']]
            ];

            $resultProjectBackup = $this->db->selectCollection('ProjectsPlaning')->aggregate($pipelineProjectBackup);
            $dataProjectBackup = array();
            foreach ($resultProjectBackup as $doc) \array_push($dataProjectBackup, $doc);

            if (count($dataProjectBackup) == 0) {
                $project_backup_id = null;
            } else {
                $project_backup_id = $dataProjectBackup[0]->project_backup_id;
            }


            $pipelineSoW = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'statement_of_work_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'SOW']]
            ];
            $resultSoW = $this->db->selectCollection('StatementOfWork')->aggregate($pipelineSoW);
            $dataSoW = array();
            foreach ($resultSoW as $doc) \array_push($dataSoW, $doc);


            if (count($dataSoW) == 0) {
                $statement_of_work_id = null;
            } else {
                $statement_of_work_id = $dataSoW[0]->statement_of_work_id;
            }

            $pipelineMaintanace = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'maintenance_docs_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'MAINTENANCE_DOC']]
            ];
            $resultMaintanace = $this->db->selectCollection('MaintenanceDocs')->aggregate($pipelineMaintanace);
            $dataMaintanace = array();
            foreach ($resultMaintanace as $doc) \array_push($dataMaintanace, $doc);


            if (count($dataMaintanace) == 0) {
                $maintenance_docs_id = null;
            } else {
                $maintenance_docs_id = $dataMaintanace[0]->maintenance_docs_id;
            }

            $pipelineOperation = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'product_operation_guide_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'PRODUCT_OPERATION_GUIDE']]
            ];
            $resultOperation = $this->db->selectCollection('ProductOperationGuide')->aggregate($pipelineOperation);
            $dataOperation = array();
            foreach ($resultOperation as $doc) \array_push($dataOperation, $doc);

            if (count($dataOperation) == 0) {
                $product_operation_guide_id = null;
            } else {
                $product_operation_guide_id = $dataOperation[0]->product_operation_guide_id;
            }

            $pipelineRegister = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'correctionregister_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'CORRECTIONREGISTER']]

            ];
            $resultRegister = $this->db->selectCollection('iCSD_Correctionregister')->aggregate($pipelineRegister);
            $dataRegister = array();
            foreach ($resultRegister as $doc) \array_push($dataRegister, $doc);

            if (count($dataRegister) == 0) {
                $correctionregister_id = null;
            } else {
                $correctionregister_id = $dataRegister[0]->correctionregister_id;
            }

            $pipelineDesign = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'software_design_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'DESIGN']]
            ];
            $resultDesign = $this->db->selectCollection('SoftwareDesign')->aggregate($pipelineDesign);
            $dataDesign = array();
            foreach ($resultDesign as $doc) \array_push($dataDesign, $doc);

            if (count($dataDesign) == 0) {
                $software_design_id = null;
            } else {
                $software_design_id = $dataDesign[0]->software_design_id;
            }

            $pipelineSWUserDocs = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]], ['$limit' => 1],
                ['$project' => ['_id' => 0, 'software_user_docs_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'USER_DOC']]
            ];
            $resultSWUserDocs = $this->db->selectCollection('SoftwareUserDocs')->aggregate($pipelineSWUserDocs);
            $dataSWUserDocs = array();
            foreach ($resultSWUserDocs as $doc) \array_push($dataSWUserDocs, $doc);


            if (count($dataSWUserDocs) == 0) {
                $software_user_docs_id = null;
            } else {
                $software_user_docs_id = $dataSWUserDocs[0]->software_user_docs_id;
            }

            $pipelineTestCases = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'test_case_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'TEST_CASES']]
            ];
            $resultTestCases = $this->db->selectCollection('TestCases')->aggregate($pipelineTestCases);
            $dataTestCases = array();
            foreach ($resultTestCases as $doc) \array_push($dataTestCases, $doc);

            if (count($dataTestCases) == 0) {
                $test_case_id = null;
            } else {
                $test_case_id = $dataTestCases[0]->test_case_id;
            }

            $pipelineTestReport = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$lookup' => ['from' => 'TestReport', 'localField' => '_id', 'foreignField' => 'test_case_id', 'as' => 'result_TestReport']],
                ['$unwind' => '$result_TestReport'],
                ['$project' => ['_id' => 0, 'test_case_id' => '$_id', 'project_id' => 1, 'test_report_id' => '$result_TestReport._id', 'test_case_created_at' => '$result_TestReport.created_at', 'verifitype_type' => 'TEST_REPORT']],
                ['$sort' => ['test_case_created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['test_report_id' => ['$toString' => '$test_report_id'], 'verifitype_type' => 'TEST_REPORT', 'project_id' => ['$toString' => '$project_id']]]
            ];
            $resultTestReport = $this->db->selectCollection('TestCases')->aggregate($pipelineTestReport);
            $dataTestReport = array();
            foreach ($resultTestReport as $doc) \array_push($dataTestReport, $doc);

            if (count($dataTestReport) == 0) {
                $test_report_id = null;
            } else {
                $test_report_id = $dataTestReport[0]->test_report_id;
            }

            $pipelineTraceability = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'traceability_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'], 'version' => 1, 'verifitype_type' => 'TRACEABILITY_RECORD']]
            ];
            $resultTraceability = $this->db->selectCollection('Traceability')->aggregate($pipelineTraceability);
            $dataTraceability = array();
            foreach ($resultTraceability as $doc) \array_push($dataTraceability, $doc);

            if (count($dataTraceability) == 0) {
                $traceability_id = null;
            } else {
                $traceability_id = $dataTraceability[0]->traceability_id;
            }

            $pipelineVV = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'vv_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id']]]
            ];
            $resultVV = $this->db->selectCollection('VerificationValidation')->aggregate($pipelineVV);
            $dataVV = array();
            foreach ($resultVV as $doc) \array_push($dataVV, $doc);

            if (count($dataVV) == 0) {
                $vv_id = null;
            } else {
                $vv_id = $dataVV[0]->vv_id;
            }

            $pipelineSoftwareComponent = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'software_component_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id']]]
            ];
            $resultSoftwareComponent = $this->db->selectCollection('SoftwareComponent')->aggregate($pipelineSoftwareComponent);
            $dataSoftwareComponent = array();
            foreach ($resultSoftwareComponent as $doc) \array_push($dataSoftwareComponent, $doc);

            if (count($dataSoftwareComponent) == 0) {
                $software_component_id = null;
            } else {
                $software_component_id = $dataSoftwareComponent[0]->software_component_id;
            }

            $pipelineSoftwareReqSpecification = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'requirements_specification_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id']]]
            ];
            $resultSoftwareReqSpecification = $this->db->selectCollection('SoftwareReqSpecification')->aggregate($pipelineSoftwareReqSpecification);
            $dataSoftwareReqSpecification = array();
            foreach ($resultSoftwareReqSpecification as $doc) \array_push($dataSoftwareReqSpecification, $doc);

            if (count($dataSoftwareReqSpecification) == 0) {
                $requirements_specification_id = null;
            } else {
                $requirements_specification_id = $dataSoftwareReqSpecification[0]->requirements_specification_id;
            }

            $pipelineChangeRequests = [
                ['$match' => ['project_id' => $this->MongoDBObjectId($projectID)]],
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
                ['$project' => ['_id' => 0, 'correctionregister_id' => ['$toString' => '$_id'], 'project_id' => ['$toString' => '$project_id'],]]
            ];
            $resultChangeRequests = $this->db->selectCollection('ChangeRequests')->aggregate($pipelineChangeRequests);
            $dataChangeRequests = array();
            foreach ($resultChangeRequests as $doc) \array_push($dataChangeRequests, $doc);

            if (count($dataChangeRequests) == 0) {
                $change_request_id = null;
            } else {
                $change_request_id = $dataChangeRequests[0]->change_request_id;
            }
            $document = [
                "project_id"                => $projectID,
                "acceptance_record_id"      => $acceptance_record_id,
                "change_request_id"         => $change_request_id,
                "correctionregister_id"     => $correctionregister_id,
                "maintenance_docs_id"       => $maintenance_docs_id,
                "meeting_record"            => null,
                "product_operation_guide_id"    => $product_operation_guide_id,
                "progress_status_record"        => $projectID,
                "project_plan_id"               => $project_plan_id,
                "project_repo_id"               => $project_repo_id,
                "project_backup_id"             => $project_backup_id,
                "requirements_specification_id"    => $requirements_specification_id,
                "software_id"                   => $projectID,
                "software_component_id"         => $software_component_id,
                "software_config_id"            => $vv_id,
                "software_design_id"            => $software_design_id,
                "software_user_docs_id"         => $software_user_docs_id,
                "statement_of_work_id"          => $statement_of_work_id,
                "test_case_id"              => $test_case_id,
                "test_report_id"            => $test_report_id,
                "traceability_id"           => $traceability_id,
                "vv_id"                     => $vv_id,

            ];


            return response()->json([
                "status" => "success",
                "message" => "Get evidences list successfully !!",
                "data" => [$document]
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    //* [GET] /statement-of-work/projects-list
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


            $pipelineCalculateMainIssuesOnly = [
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
                ['$unwind' => '$result'],
                ['$project' => [
                    '_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'project_id' => '$_id', 'is_closed' => 1, 'project_type_id' => 1, 'project_type' => 1,
                    'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup', 'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status', 'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by', 'selling_prices' => '$result.selling_prices'
                ]],
                ['$group' => [
                    '_id' => '$_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'version' => ['$last' => '$version'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'is_closed' => ['$last' => '$is_closed'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'],
                    'project_plan' => ['$push' => ['job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments', 'version' => '$version', 'is_edit' => '$is_edit', 'job_order' => '$job_order', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at', 'verified_by' => '$verified_by', 'status' => '$status']]
                ]],
                ['$unwind' => '$project_plan'],
                ['$project' => [
                    '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'creator_id' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'project_type' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'job_order' => '$project_plan.job_order', 'project_plan_id' => '$project_plan.project_plan_id', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'responsibility' => '$project_plan.responsibility', 'equipments' => '$project_plan.equipments',
                    'version' => '$project_plan.version', 'is_edit' => '$project_plan.is_edit', 'project_plan_created_at' => '$project_plan.project_plan_created_at', 'project_plan_updated_at' => '$project_plan.project_plan_updated_at', 'verified_by' => '$project_plan.verified_by', 'status' => '$project_plan.status', 'selling_prices' => '$project_plan.selling_prices'
                ]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_id' => ['$last' => '$project_id'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'],
                    'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'status' => ['$last' => '$status'], 'responsibility' => ['$last' => '$responsibility'], 'equipments' => ['$last' => '$equipments'], 'version' => ['$last' => '$version'], 'status' => ['$last' => '$status'], 'selling_prices' => ['$last' => '$selling_prices'], 'verified_by' => ['$last' => '$verified_by'], 'project_plan_updated_at' => ['$last' => '$project_plan_updated_at'], 'project_plan_created_at' => ['$last' => '$project_plan_created_at']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'verified_by' => 1, 'version' => 1, 'equipments' => 1, 'responsibility' => 1, 'status' => 1, 'project_backup' => 1, 'project_repo' => 1, 'project_plan_id' => 1, 'job_order' => 1, 'selling_prices' => 1, 'project_plan_created_at' => 1, 'project_plan_updated_at' => 1]],
                ['$unwind' => '$responsibility'],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
                ['$project' => ['role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'is_closed' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'is_closed' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'version_PP' => '$version', 'job_order' => 1, 'is_edit' => 1, 'status' => 1, 'account_id' => '$responsibility.account_id']],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
                ['$group' => [
                    '_id' => '$project_id', 'position_name' => ['$last' => '$position_name'], 'user_id' => ['$last' => '$user_id'], 'role_name' => ['$last' => '$role_name'], 'name' => ['$last' => '$name'], 'position_id' => ['$last' => '$position_id'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'status' => ['$last' => '$status'], 'equipments' => ['$last' => '$equipments'], 'project_backup' => ['$last' => '$project_backup'], 'project_name' => ['$last' => '$project_name'],
                    'creator_id' => ['$last' => '$creator_id'], 'project_id' => ['$last' => '$project_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'updated_at' => ['$last' => '$updated_at'], 'created_at' => ['$last' => '$created_at'], 'job_order' => ['$last' => '$job_order'], 'is_closed' => ['$last' => '$is_closed'], 'responsibility' => ['$push' => ['user_id' => ['$toString' => '$user_id'], 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
                ]],
                ['$project' => ['_id' => 0, 'project_id' => '$_id', 'creator_id' => ['$toString' => '$creator_id'], 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'project_type' => 1, 'project_type_id' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
                ['$unwind' => '$statementofwork'],
                ['$project' => [
                    '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'project_id' => 1, 'job_order' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1, 'version_PP' => 1, 'is_edit' => 1, 'status' => 1,
                    'user_id' => ['$toString' => '$user_id'], 'version_SoW' => '$statementofwork.version', 'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
                ]],
                ['$sort' => ['createdSOW_at' => -1]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'project_id' => ['$last' => '$project_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'updated_at' => ['$last' => '$updated_at'], 'created_at' => ['$last' => '$created_at'], 'job_order' => ['$last' => '$job_order'], 'is_closed' => ['$last' => '$is_closed'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date'],
                    'version_SoW' => ['$last' => '$version_SoW'], 'responsibility' => ['$last' => '$responsibility']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => '$_id', 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'project_type' => 1, 'project_type_id' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'start_date' => 1, 'end_date' => 1]],
                ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'responsibility' => 1, 'job_order' => 1, 'project_type' => 1, 'project_name' => 1, 'is_closed' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'issues' => 1, 'start_date' => 1, 'end_date' => 1]],
                ['$unwind' => '$issues'],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$issues', '$$ROOT']]]],
                ['$match' => ['parent_id' => null]],
                ['$group' => [
                    '_id' => '$project_id', 'creator_id' => ['$first' => '$creator_id'], 'teamspace_id' => ['$first' => '$teamspace_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'project_name' => ['$first' => '$project_name'], 'customer_name' => ['$first' => '$customer_name'], 'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'responsibility' => ['$first' => '$responsibility'], 'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'],
                    'start_date' => ['$first' => '$start_date'], 'end_date' => ['$first' => '$end_date'], 'is_approved_null' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', null]], 1, 0]]], 'is_approved_true' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', true]], 1, 0]]], 'is_approved_false' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', false]], 1, 0]]]
                ]],
                ['$addFields' => ['progress' => ['$cond' => [['$eq' => ['$is_approved_true', 0]], 0, ['$multiply' => [['$divide' => ['$is_approved_true', ['$add' => ['$is_approved_true', '$is_approved_null']]]], 100]]]]]],
                ['$project' => [
                    '_id' => 0, 'progress' => 1, 'is_closed' => 1, 'project_id' => ['$toObjectId' => '$_id'], 'creator_id' => '$creator_id', 'teamspace_id' => ['$toString' => '$teamspace_id'], 'job_order' => 1, 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'traceability' => ['$arrayElemAt' => ['$traceability', 0]], 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'job_order' => 1, 'start_date' => 1, 'end_date' => 1
                ]],
                ['$project' => ['_id' => 0, 'progress' => 1, 'is_closed' => 1, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'job_order' => 1, 'start_date' => 1, 'end_date' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'is_validated' => 1, 'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'progress' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'result_Approved' => 1, 'start_date' => 1, 'end_date' => 1]],
                ['$project' => [
                    '_id' => 0, 'progress' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'start_date' => 1, 'end_date' => 1,
                    'result_Approved' => ['$map' => ['input' => '$result_Approved', 'as' => 'respp', 'in' => ['project_id' => ['$toString' => '$$respp.project_id'], 'verification_type' => '$$respp.verification_type', 'is_validated' => '$$respp.is_validated', 'createdApproved_at' => '$$respp.createdApproved_at']]]
                ]],
                ['$project' => [
                    '_id' => 0, 'progress' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'start_date' => 1, 'end_date' => 1, 'progress' => 1,
                    'result_Approved' => ['$filter' => ['input' => '$result_Approved', 'as' => 'approved', 'cond' => ['$eq' => ['$$approved.verification_type', 'PROJECT_PLAN']]]]
                ]],
                ['$unwind' => '$result_Approved'],
                ['$project' => [
                    '_id' => 0, 'progress' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'end_date' => 1, 'start_date' => 1, 'end_date' => 1, 'start_date' => 1, 'progress' => 1,
                    'verification_type' => '$result_Approved.verification_type', 'is_validated' => '$result_Approved.is_validated', 'createdApproved_at' => '$result_Approved.createdApproved_at'
                ]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'progress' => ['$last' => '$progress'], 'project_id' => ['$last' => '$project_id'], 'is_closed' => ['$last' => '$is_closed'], 'responsibility' => ['$last' => '$responsibility'], 'creator_id' => ['$last' => '$creator_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'job_order' => ['$last' => '$job_order'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'],
                    'is_approved_null' => ['$last' => '$is_approved_null'], 'is_approved_true' => ['$last' => '$is_approved_true'], 'is_approved_false' => ['$last' => '$is_approved_false'], 'verification_type' => ['$last' => '$verification_type'], 'is_validated' => ['$last' => '$is_validated'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date']
                ]],
                ['$project' => ['_id' => 0, 'progress' => 1, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'is_closed' => 1, 'responsibility' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'prject_type' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'is_validated' => 1, 'end_date' => 1, 'start_date' => 1]]
            ];

            // $pipelineCalculateMainIssuesOnlyOld = [
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
            //     ['$unwind' => '$result'],
            //     ['$project' => [
            //         '_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'project_id' => '$_id', 'is_closed' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup',
            //         'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status', 'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by', 'selling_prices' => '$result.selling_prices'
            //     ]],
            //     ['$group' => [
            //         '_id' => '$_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'created_at' => ['$last' => '$created_at'],
            //         'updated_at' => ['$last' => '$updated_at'], 'is_closed' => ['$last' => '$is_closed'], 'project_plan' => ['$push' => ['job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments', 'version' => '$version', 'is_edit' => '$is_edit', 'is_edit' => '$is_edit', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at', 'verified_by' => '$verified_by', 'status' => '$status', 'job_order' => '$job_order', 'selling_prices' => '$selling_prices']]
            //     ]],
            //     ['$unwind' => '$project_plan'],
            //     ['$project' => [
            //         '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'creator_id' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'project_type' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'job_order' => '$project_plan.job_order', 'project_plan_id' => '$project_plan.project_plan_id', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'responsibility' => '$project_plan.responsibility', 'equipments' => '$project_plan.equipments', 'version' => '$project_plan.version', 'is_edit' => '$project_plan.is_edit', 'project_plan_created_at' => '$project_plan.project_plan_created_at',
            //         'project_plan_updated_at' => '$project_plan.project_plan_updated_at', 'verified_by' => '$project_plan.verified_by', 'status' => '$project_plan.status', 'selling_prices' => '$project_plan.selling_prices'
            //     ]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_id' => ['$last' => '$project_id'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'project_plan_id' => ['$last' => '$project_plan_id'],
            //         'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'status' => ['$last' => '$status'], 'responsibility' => ['$last' => '$responsibility'], 'equipments' => ['$last' => '$equipments'], 'version' => ['$last' => '$version'], 'status' => ['$last' => '$status'], 'selling_prices' => ['$last' => '$selling_prices'], 'verified_by' => ['$last' => '$verified_by'], 'project_plan_updated_at' => ['$last' => '$project_plan_updated_at'], 'project_plan_created_at' => ['$last' => '$project_plan_created_at']
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'verified_by' => 1, 'version' => 1, 'equipments' => 1, 'responsibility' => 1, 'status' => 1, 'project_backup' => 1, 'project_repo' => 1, 'project_plan_id' => 1, 'job_order' => 1, 'selling_prices' => 1, 'project_plan_created_at' => 1, 'project_plan_updated_at' => 1]],
            //     ['$unwind' => '$responsibility'],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
            //     ['$project' => ['role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'is_closed' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'is_closed' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'version_PP' => '$version', 'job_order' => 1, 'is_edit' => 1, 'status' => 1, 'account_id' => '$responsibility.account_id']],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
            //     ['$unwind' => '$statementofwork'],
            //     ['$project' => [
            //         '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'project_id' => 1, 'job_order' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1,
            //         'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1, 'version_PP' => 1, 'is_edit' => 1, 'status' => 1, 'user_id' => ['$toString' => '$user_id'], 'version_SoW' => '$statementofwork.version', 'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
            //     ]],
            //     ['$sort' => ['createdSOW_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'project_id' => ['$first' => '$project_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_name' => ['$first' => '$project_name'], 'project_type' => ['$first' => '$project_type'], 'customer_name' => ['$first' => '$customer_name'],
            //         'updated_at' => ['$first' => '$updated_at'], 'created_at' => ['$first' => '$created_at'], 'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'], 'start_date' => ['$first' => '$start_date'], 'end_date' => ['$first' => '$end_date'], 'traceability' => ['$push' => ['version' => '$version', 'start_date' => '$start_date', 'end_date' => '$end_date', 'createdSOW_at' => '$createdSOW_at']], 'responsibility' => ['$push' => ['user_id' => '$user_id', 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
            //     ]],
            //     ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'responsibility' => 1, 'traceability' => 1, 'job_order' => 1, 'project_type' => 1, 'project_name' => 1, 'is_closed' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'issues' => 1, 'start_date' => 1, 'end_date' => 1]],
            //     ['$unwind' => '$issues'],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$issues', '$$ROOT']]]],
            //     ['$match' => ['parent_id' => null]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'creator_id' => ['$first' => '$creator_id'], 'teamspace_id' => ['$first' => '$teamspace_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'project_name' => ['$first' => '$project_name'], 'customer_name' => ['$first' => '$customer_name'], 'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'responsibility' => ['$first' => '$responsibility'], 'traceability' => ['$first' => '$traceability'],
            //         'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'], 'start_date' => ['$first' => '$start_date'], 'end_date' => ['$first' => '$end_date'], 'is_approved_null' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', null]], 1, 0]]], 'is_approved_true' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', true]], 1, 0]]], 'is_approved_false' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', false]], 1, 0]]]
            //     ]],
            //     ['$addFields' => ['progress' => ['$cond' => [['$eq' => ['$is_approved_true', 0]], 0, ['$multiply' => [['$divide' => ['$is_approved_true', ['$add' => ['$is_approved_true', '$is_approved_null']]]], 100]]]]]],
            //     ['$project' => [
            //         '_id' => 0, 'is_closed' => 1, 'project_id' => ['$toObjectId' => '$_id'], 'creator_id' => '$creator_id', 'teamspace_id' => ['$toString' => '$teamspace_id'], 'job_order' => 1, 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => 'customer_name', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         'traceability' => ['$arrayElemAt' => ['$traceability', 0]], 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'job_order' => 1, 'start_date' => 1, 'end_date' => 1
            //     ]],
            //     ['$project' => ['_id' => 0, 'is_closed' => 1, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'job_order' => 1, 'start_date' => 1, 'end_date' => 1]],
            //     ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'is_validated' => 1, 'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'result_Approved' => 1, 'start_date' => 1, 'end_date' => 1]],
            //     ['$project' => [
            //         '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'start_date' => 1, 'end_date' => 1,
            //         'result_Approved' => ['$map' => ['input' => '$result_Approved', 'as' => 'respp', 'in' => ['project_id' => ['$toString' => '$$respp.project_id'], 'verification_type' => '$$respp.verification_type', 'is_validated' => '$$respp.is_validated', 'createdApproved_at' => '$$respp.createdApproved_at']]]
            //     ]],
            //     ['$project' => [
            //         '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'start_date' => 1, 'end_date' => 1, 'progress' => 1,
            //         'result_Approved' => ['$filter' => ['input' => '$result_Approved', 'as' => 'approved', 'cond' => ['$eq' => ['$$approved.verification_type', 'PROJECT_PLAN']]]]
            //     ]],
            //     ['$unwind' => '$result_Approved'],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'start_date' => 1, 'end_date' => 1, 'verification_type' => '$result_Approved.verification_type', 'is_validated' => '$result_Approved.is_validated', 'createdApproved_at' => '$result_Approved.createdApproved_at']],
            //     // ['$sort' => ['createdApproved_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$first' => '$project_name'], 'project_id' => ['$first' => '$project_id'], 'is_closed' => ['$first' => '$is_closed'], 'responsibility' => ['$first' => '$responsibility'], 'creator_id' => ['$first' => '$creator_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'customer_name' => ['$first' => '$customer_name'],
            //         'job_order' => ['$first' => '$job_order'], 'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'is_approved_null' => ['$first' => '$is_approved_null'], 'is_approved_true' => ['$first' => '$is_approved_true'], 'is_approved_false' => ['$first' => '$is_approved_false'], 'verification_type' => ['$first' => '$verification_type'], 'is_validated' => ['$first' => '$is_validated'], 'start_date' => ['$first' => '$start_date'], 'end_date' => ['$first' => '$end_date']
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, "project_type" => 1, 'is_closed' => 1, 'responsibility' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'is_validated' => 1, 'start_date' => 1, 'end_date' => 1]]
            // ];

            // $pipelineCalculateMainIssuesOnlyOld = [
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
            //     ['$unwind' => '$result'],
            //     ['$project' => ['_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup', 'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status', 'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by']],
            //     ['$sort' => ['project_plan_created_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'customer_name' => ['$first' => '$customer_name'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'statement_of_work_id' => ['$first' => '$statement_of_work_id'], 'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'is_closed' => ['$first' => '$is_closed'],
            //         'project_plan' => ['$push' => [
            //             'job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments', 'version' => '$version', 'is_edit' => '$is_edit', 'is_edit' => '$is_edit', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at',
            //             'verified_by' => '$verified_by', 'status' => '$status'
            //         ]]
            //     ]],
            //     ['$project' => ['_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan' => ['$arrayElemAt' => ['$project_plan', 0]]]],
            //     ['$project' => [
            //         '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'project_type' => 1, 'creator_id' => 1, 'is_closed' => '$is_closed', 'customer_name' => 1, 'project_type_id' => 1, 'statement_of_work_id' => '$project_plan.statement_of_work_id', 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => '$project_plan.project_plan_id', 'responsibility' => '$project_plan.responsibility', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'equipments' => '$project_plan.equipments', 'version' => '$project_plan.version',
            //         'is_edit' => '$project_plan.is_edit', 'status' => '$project_plan.status', 'job_order' => '$project_plan.job_order'
            //     ]],
            //     ['$unwind' => '$responsibility'],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
            //     ['$project' => [
            //         'role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'is_closed' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'is_closed' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'version_PP' => '$version', 'job_order' => 1,
            //         'is_edit' => 1, 'status' => 1, 'account_id' => '$responsibility.account_id'
            //     ]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
            //     ['$unwind' => '$statementofwork'],
            //     ['$project' => [
            //         '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'project_id' => 1,
            //         'job_order' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1, 'version_PP' => 1, 'is_edit' => 1, 'status' => 1, 'user_id' => ['$toString' => '$user_id'], 'version_SoW' => '$statementofwork.version',
            //         'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
            //     ]],
            //     ['$sort' => ['createdSOW_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'project_id' => ['$first' => '$project_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_name' => ['$first' => '$project_name'], 'project_type' => ['$first' => '$project_type'],
            //         'customer_name' => ['$first' => '$customer_name'], 'updated_at' => ['$first' => '$updated_at'], 'created_at' => ['$first' => '$created_at'], 'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'], 'traceability' => ['$push' => [
            //             'version' => '$version', 'start_date' => '$start_date', 'end_date' => '$end_date',
            //             'createdSOW_at' => '$createdSOW_at'
            //         ]], 'responsibility' => ['$push' => ['user_id' => '$user_id', 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
            //     ]],
            //     ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'responsibility' => 1, 'traceability' => 1, 'job_order' => 1, 'project_type' => 1, 'project_name' => 1, 'is_closed' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'issues' => 1]],
            //     ['$unwind' => '$issues'],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => ['$issues', '$$ROOT']]]],
            //     ['$match' => ['parent_id' => null]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'creator_id' => ['$first' => '$creator_id'], 'teamspace_id' => ['$first' => '$teamspace_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'project_name' => ['$first' => '$project_name'], 'customer_name' => ['$first' => '$customer_name'],
            //         'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'responsibility' => ['$first' => '$responsibility'], 'traceability' => ['$first' => '$traceability'], 'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'], 'is_approved_null' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', null]], 1, 0]]],
            //         'is_approved_true' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', true]], 1, 0]]], 'is_approved_false' => ['$sum' => ['$cond' => [['$eq' => ['$is_approved', false]], 1, 0]]]
            //     ]],
            //     ['$addFields' => ['progress' => ['$cond' => [['$eq' => ['$is_approved_true', 0]], 0, ['$multiply' => [['$divide' => ['$is_approved_true', ['$add' => ['$is_approved_true', '$is_approved_null']]]], 100]]]]]],
            //     ['$project' => [
            //         '_id' => 0, 'is_closed' => 1, 'project_id' => '$_id', 'creator_id' => '$creator_id', 'teamspace_id' => ['$toString' => '$teamspace_id'], 'job_order' => 1, 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => '$customer_name',
            //         'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'traceability' => ['$arrayElemAt' => ['$traceability', 0]], 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'job_order' => 1
            //     ]],
            //     ['$project' => [
            //         '_id' => 0, 'is_closed' => 1, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1,
            //         'version' => '$traceability.version', 'start_date' => '$traceability.start_date', 'end_date' => '$traceability.end_date'
            //     ]]
            // ];

            // $pipelineNonIssuesOld = [
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
            //     ['$unwind' => '$result'],
            //     ['$project' => [
            //         '_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup',
            //         'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status', 'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by'
            //     ]],
            //     ['$sort' => ['project_plan_created_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'customer_name' => ['$first' => '$customer_name'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'statement_of_work_id' => ['$first' => '$statement_of_work_id'], 'is_closed' => ['$first' => '$is_closed'],
            //         'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'project_plan' => ['$push' => [
            //             'job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments',
            //             'version' => '$version', 'is_edit' => '$is_edit', 'job_order' => '$job_order', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at', 'verified_by' => '$verified_by', 'status' => '$status'
            //         ]]
            //     ]],
            //     ['$project' => ['_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'project_plan' => ['$arrayElemAt' => ['$project_plan', 0]]]],
            //     ['$project' => [
            //         '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'project_type' => 1, 'creator_id' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'is_closed' => 1, 'statement_of_work_id' => '$project_plan.statement_of_work_id', 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => '$project_plan.project_plan_id',
            //         'responsibility' => '$project_plan.responsibility', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'equipments' => '$project_plan.equipments', 'version' => '$project_plan.version', 'is_edit' => '$project_plan.is_edit', 'status' => '$project_plan.status', 'job_order' => '$project_plan.job_order'
            //     ]],
            //     ['$unwind' => '$responsibility'],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
            //     ['$project' => [
            //         'role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1,
            //         'version_PP' => '$version', 'job_order' => 1, 'is_edit' => 1, 'status' => 1, 'is_closed' => 1, 'account_id' => '$responsibility.account_id'
            //     ]],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
            //     ['$unwind' => '$statementofwork'],
            //     ['$project' => [
            //         '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_id' => 1, 'job_order' => 1,
            //         'is_closed' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1, 'version_PP' => 1, 'is_edit' => 1, 'status' => 1, 'user_id' => ['$toString' => '$user_id'], 'version_SoW' => '$statementofwork.version',
            //         'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
            //     ]],
            //     ['$sort' => ['createdSOW_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'project_id' => ['$first' => '$project_id'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'customer_name' => ['$first' => '$customer_name'],
            //         'updated_at' => ['$first' => '$updated_at'], 'created_at' => ['$first' => '$created_at'], 'job_order' => ['$first' => '$job_order'], 'is_closed' => ['$first' => '$is_closed'], 'traceability' => ['$push' => ['version' => '$version', 'start_date' => '$start_date', 'end_date' => '$end_date', 'createdSOW_at' => '$createdSOW_at']],
            //         'responsibility' => ['$push' => ['user_id' => '$user_id', 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
            //     ]],
            //     ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
            //     ['$project' => [
            //         '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'responsibility' => 1, 'traceability' => 1, 'job_order' => 1, 'project_type' => 1, 'project_name' => 1, 'is_closed' => 1,
            //         'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'issues' => 1
            //     ]],
            //     ['$match' => ['issues' => []]],
            //     ['$project' => [
            //         '_id' => 0, 'is_closed' => 1, 'project_id' => '$project_id', 'creator_id' => '$creator_id', 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'job_order' => '$job_order',
            //         'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         'traceability' => ['$arrayElemAt' => ['$traceability', 0]], 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1
            //     ]],
            //     ['$project' => [
            //         '_id' => 0, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1,
            //         'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'version' => '$traceability.version', 'start_date' => '$traceability.start_date', 'end_date' => '$traceability.end_date', 'is_approved_null' => null, 'is_approved_true' => null,
            //         'is_approved_false' => null, 'progress' => null,
            //     ]],
            // ];

            // $pipelineNonIssuesOld = [
            //     ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
            //     ['$unwind' => '$result'],
            //     ['$project' => [
            //         '_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup', 'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status',
            //         'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by', 'selling_prices' => '$result.selling_prices'
            //     ]],
            //     ['$group' => [
            //         '_id' => '$_id', 'project_name' => ['$first' => '$project_name'], 'creator_id' => ['$first' => '$creator_id'], 'customer_name' => ['$first' => '$customer_name'], 'project_type_id' => ['$first' => '$project_type_id'], 'project_type' => ['$first' => '$project_type'], 'statement_of_work_id' => ['$first' => '$statement_of_work_id'],
            //         'is_closed' => ['$first' => '$is_closed'], 'created_at' => ['$first' => '$created_at'], 'updated_at' => ['$first' => '$updated_at'], 'project_plan' => ['$push' => ['job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments', 'version' => '$version', 'is_edit' => '$is_edit', 'job_order' => '$job_order', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at', 'verified_by' => '$verified_by', 'status' => '$status']]
            //     ]],
            //     ['$unwind' => '$project_plan'],
            //     ['$project' => [
            //         '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'creator_id' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'project_type' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'job_order' => '$project_plan.job_order', 'project_plan_id' => '$project_plan.project_plan_id', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'responsibility' => '$project_plan.responsibility', 'equipments' => '$project_plan.equipments',
            //         'version' => '$project_plan.version', 'is_edit' => '$project_plan.is_edit', 'project_plan_created_at' => '$project_plan.project_plan_created_at', 'project_plan_updated_at' => '$project_plan.project_plan_updated_at', 'verified_by' => '$project_plan.verified_by', 'status' => '$project_plan.status', 'selling_prices' => '$project_plan.selling_prices'
            //     ]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_id' => ['$last' => '$project_id'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'],
            //         'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'status' => ['$last' => '$status'], 'responsibility' => ['$last' => '$responsibility'], 'equipments' => ['$last' => '$equipments'], 'version' => ['$last' => '$version'], 'status' => ['$last' => '$status'], 'selling_prices' => ['$last' => '$selling_prices'], 'verified_by' => ['$last' => '$verified_by'], 'project_plan_updated_at' => ['$last' => '$project_plan_updated_at'], 'project_plan_created_at' => ['$last' => '$project_plan_created_at']
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'verified_by' => 1, 'version' => 1, 'equipments' => 1, 'responsibility' => 1, 'status' => 1, 'project_backup' => 1, 'project_repo' => 1, 'project_plan_id' => 1, 'job_order' => 1, 'selling_prices' => 1, 'project_plan_created_at' => 1, 'project_plan_updated_at' => 1]],
            //     ['$unwind' => '$responsibility'],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
            //     ['$project' => ['role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'version_PP' => '$version', 'job_order' => 1, 'is_edit' => 1, 'status' => 1, 'is_closed' => 1, 'account_id' => '$responsibility.account_id']],
            //     ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
            //     ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
            //     ['$unwind' => '$statementofwork'],
            //     ['$project' => [
            //         '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_id' => 1, 'job_order' => 1, 'is_closed' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1, 'version_PP' => 1, 'is_edit' => 1, 'status' => 1, 'user_id' => ['$toString' => '$user_id'],
            //         'version_SoW' => '$statementofwork.version', 'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
            //     ]],
            //     ['$sort' => ['createdSOW_at' => -1]],

            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'project_id' => ['$last' => '$project_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'updated_at' => ['$last' => '$updated_at'], 'created_at' => ['$last' => '$created_at'],
            //         'job_order' => ['$last' => '$job_order'], 'is_closed' => ['$last' => '$is_closed'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date'], 'traceability' => ['$push' => ['version' => '$version', 'start_date' => '$start_date', 'end_date' => '$end_date', 'createdSOW_at' => '$createdSOW_at']], 'responsibility' => ['$push' => ['user_id' => '$user_id', 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
            //     ]],
            //     ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'responsibility' => 1, 'traceability' => 1, 'job_order' => 1, 'project_type' => 1, 'project_name' => 1, 'is_closed' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'issues' => 1, 'end_date' => 1, 'start_date' => 1]],
            //     ['$match' => ['issues' => []]],
            //     ['$project' => [
            //         '_id' => 0, 'is_closed' => 1, 'project_id' => '$project_id', 'creator_id' => '$creator_id', 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'job_order' => '$job_order', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']],
            //         'traceability' => ['$arrayElemAt' => ['$traceability', 0]], 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'end_date' => 1, 'start_date' => 1
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'progress' => 1, 'responsibility' => 1, 'is_approved_null' => null, 'is_approved_true' => null, 'is_approved_false' => null, 'progress' => null, 'end_date' => 1, 'start_date' => 1]],
            //     ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'is_validated' => 1, 'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at']]]]],
            //     ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'progress' => null, 'result_Approved' => 1, 'end_date' => 1, 'start_date' => 1]],
            //     ['$project' => [
            //         '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'version' => '$traceability.version', 'progress' => null, 'end_date' => 1, 'start_date' => 1,
            //         'result_Approved' => ['$map' => ['input' => '$result_Approved', 'as' => 'respp', 'in' => ['project_id' => ['$toString' => '$$respp.project_id'], 'verification_type' => '$$respp.verification_type', 'is_validated' => '$$respp.is_validated', 'createdApproved_at' => '$$respp.createdApproved_at']]]
            //     ]],
            //     ['$project' => [
            //         '_id' => 0, 'end_date' => 1, 'start_date' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'progress' => null,
            //         'result_Approved' => ['$filter' => ['input' => '$result_Approved', 'as' => 'approved', 'cond' => ['$eq' => ['$$approved.verification_type', 'PROJECT_PLAN']]]]
            //     ]],
            //     ['$unwind' => '$result_Approved'],
            //     ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'end_date' => 1, 'start_date' => 1,  'progress' => null, 'verification_type' => '$result_Approved.verification_type', 'is_validated' => '$result_Approved.is_validated', 'createdApproved_at' => '$result_Approved.createdApproved_at']],
            //     ['$sort' => ['createdApproved_at' => -1]],
            //     ['$group' => [
            //         '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_id' => ['$last' => '$project_id'], 'is_closed' => ['$last' => '$is_closed'], 'responsibility' => ['$last' => '$responsibility'], 'creator_id' => ['$last' => '$creator_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'job_order' => ['$last' => '$job_order'],
            //         'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'is_approved_null' => ['$last' => '$is_approved_null'], 'is_approved_true' => ['$last' => '$is_approved_true'], 'is_approved_false' => ['$last' => '$is_approved_false'], 'verification_type' => ['$last' => '$verification_type'], 'is_validated' => ['$last' => '$is_validated'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date']
            //     ]],
            //     ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'is_validated' => 1, 'end_date' => 1, 'start_date' => 1]]
            // ];

            $pipelineNonIssues = [
                ['$lookup' => ['from' => 'ProjectsPlaning', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result']],
                ['$unwind' => '$result'],
                ['$project' => [
                    '_id' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'creator_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'statement_of_work_id' => '$result.statement_of_work_id', 'project_plan_id' => '$result._id', 'job_order' => '$result.job_order', 'project_repo' => '$result.project_repo', 'project_backup' => '$result.project_backup',
                    'software_requirement' => '$resultsoftware_requirement', 'responsibility' => '$result.responsibility', 'equipments' => '$result.equipments', 'version' => '$result.version', 'is_edit' => '$result.is_edit', 'status' => '$result.status', 'project_plan_created_at' => '$result.created_at', 'project_plan_updated_at' => '$result.updated_at', 'verified_by' => '$result.verified_by', 'selling_prices' => '$result.selling_prices'
                ]],
                ['$group' => [
                    '_id' => '$_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'version' => ['$last' => '$version'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'is_closed' => ['$last' => '$is_closed'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'],
                    'project_plan' => ['$push' => ['job_order' => '$job_order', 'statement_of_work_id' => '$statement_of_work_id', 'project_plan_id' => '$project_plan_id', 'project_repo' => '$project_repo', 'project_backup' => '$project_backup', 'responsibility' => '$responsibility', 'equipments' => '$equipments', 'version' => '$version', 'is_edit' => '$is_edit', 'job_order' => '$job_order', 'project_plan_created_at' => '$project_plan_created_at', 'project_plan_updated_at' => '$project_plan_updated_at', 'verified_by' => '$verified_by', 'status' => '$status']]
                ]],
                ['$unwind' => '$project_plan'],
                ['$project' => [
                    '_id' => 1, 'project_id' => '$_id', 'project_name' => 1, 'creator_id' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'project_type' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_closed' => 1, 'job_order' => '$project_plan.job_order', 'project_plan_id' => '$project_plan.project_plan_id', 'project_repo' => '$project_plan.project_repo', 'project_backup' => '$project_plan.project_backup', 'responsibility' => '$project_plan.responsibility', 'equipments' => '$project_plan.equipments', 'version' => '$project_plan.version',
                    'is_edit' => '$project_plan.is_edit', 'project_plan_created_at' => '$project_plan.project_plan_created_at', 'project_plan_updated_at' => '$project_plan.project_plan_updated_at', 'verified_by' => '$project_plan.verified_by', 'status' => '$project_plan.status', 'selling_prices' => '$project_plan.selling_prices'
                ]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_id' => ['$last' => '$project_id'], 'creator_id' => ['$last' => '$creator_id'], 'customer_name' => ['$last' => '$customer_name'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'],
                    'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'], 'is_closed' => ['$last' => '$is_closed'], 'job_order' => ['$last' => '$job_order'], 'project_plan_id' => ['$last' => '$project_plan_id'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'], 'status' => ['$last' => '$status'], 'responsibility' => ['$last' => '$responsibility'], 'equipments' => ['$last' => '$equipments'], 'version' => ['$last' => '$version'], 'status' => ['$last' => '$status'], 'selling_prices' => ['$last' => '$selling_prices'], 'verified_by' => ['$last' => '$verified_by'], 'project_plan_updated_at' => ['$last' => '$project_plan_updated_at'], 'project_plan_created_at' => ['$last' => '$project_plan_created_at']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'customer_name' => 1, 'creator_id' => 1, 'is_closed' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'verified_by' => 1, 'version' => 1, 'equipments' => 1, 'responsibility' => 1, 'status' => 1, 'project_backup' => 1, 'project_repo' => 1, 'project_plan_id' => 1, 'job_order' => 1, 'selling_prices' => 1, 'project_plan_created_at' => 1, 'project_plan_updated_at' => 1]],
                ['$unwind' => '$responsibility'],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'responsibility.account_id', 'foreignField' => '_id', 'as' => 'result_acc', 'pipeline' => [['$project' => ['_id' => 0, 'name' => '$name_en', 'position_id' => '$position_id']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_acc', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'RoleResponsibility', 'localField' => 'responsibility.role_id', 'foreignField' => '_id', 'as' => 'result_role', 'pipeline' => [['$project' => ['_id' => 0, 'role_name' => '$name']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_role', 0]], '$$ROOT']]]],
                ['$project' => ['role_name' => 1, 'name' => 1, 'position_id' => 1, 'project_id' => 1, 'project_name' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'statement_of_work_id' => 1, 'project_type' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'version_PP' => '$version', 'job_order' => 1, 'is_edit' => 1, 'status' => 1, 'is_closed' => 1, 'account_id' => '$responsibility.account_id']],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'account_id', 'foreignField' => '_id', 'as' => 'result_userID', 'pipeline' => [['$project' => ['_id' => 0, 'user_id' => 1]]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_userID', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'Positions', 'localField' => 'position_id', 'foreignField' => '_id', 'as' => 'result_position', 'pipeline' => [['$project' => ['_id' => 0, 'position_name' => '$Position']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_position', 0]], '$$ROOT']]]],
                ['$group' => [
                    '_id' => '$project_id', 'position_name' => ['$last' => '$position_name'], 'user_id' => ['$last' => '$user_id'], 'role_name' => ['$last' => '$role_name'], 'name' => ['$last' => '$name'], 'position_id' => ['$last' => '$position_id'], 'statement_of_work_id' => ['$last' => '$statement_of_work_id'], 'project_repo' => ['$last' => '$project_repo'], 'project_backup' => ['$last' => '$project_backup'],
                    'status' => ['$last' => '$status'], 'equipments' => ['$last' => '$equipments'], 'project_backup' => ['$last' => '$project_backup'], 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'project_id' => ['$last' => '$project_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'updated_at' => ['$last' => '$updated_at'], 'created_at' => ['$last' => '$created_at'], 'job_order' => ['$last' => '$job_order'], 'is_closed' => ['$last' => '$is_closed'], 'responsibility' => ['$push' => ['user_id' => ['$toString' => '$user_id'], 'name' => '$name', 'role_name' => '$role_name', 'position_name' => '$position_name']]
                ]],
                ['$project' => ['_id' => 0, 'project_id' => '$_id', 'creator_id' => ['$toString' => '$creator_id'], 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'project_type' => 1, 'project_type_id' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'statementofwork', 'pipeline' => [['$project' => ['_id' => 0, 'start_date' => 1, 'end_date' => 1, 'version' => 1, 'created_at' => 1]]]]],
                ['$unwind' => '$statementofwork'],
                ['$project' => [
                    '_id' => 1, 'position_name' => 1, 'role_name' => 1, 'name' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'project_type_id' => 1, 'creator_id' => 1, 'created_at' => 1, 'updated_at' => 1, 'project_id' => 1, 'job_order' => 1, 'is_closed' => 1, 'project_plan_id' => 1, 'responsibility' => 1, 'project_repo' => 1, 'project_backup' => 1, 'equipments' => 1, 'statement_of_work_id' => 1,
                    'version_PP' => 1, 'is_edit' => 1, 'status' => 1, 'user_id' => ['$toString' => '$user_id'], 'version_SoW' => '$statementofwork.version', 'start_date' => '$statementofwork.start_date', 'end_date' => '$statementofwork.end_date', 'createdSOW_at' => '$statementofwork.created_at'
                ]],
                ['$sort' => ['createdSOW_at' => -1]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'creator_id' => ['$last' => '$creator_id'], 'project_id' => ['$last' => '$project_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_name' => ['$last' => '$project_name'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'updated_at' => ['$last' => '$updated_at'],
                    'created_at' => ['$last' => '$created_at'], 'job_order' => ['$last' => '$job_order'], 'is_closed' => ['$last' => '$is_closed'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date'], 'version_SoW' => ['$last' => '$version_SoW'], 'responsibility' => ['$last' => '$responsibility']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => '$_id', 'creator_id' => ['$toString' => '$creator_id'], 'project_type_id' => ['$toString' => '$project_type_id'], 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'project_type' => 1, 'project_type_id' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'start_date' => 1, 'end_date' => 1]],
                ['$lookup' => ['from' => 'ProjectsIssue', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'issues']],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'creator_id' => 1, 'project_type_id' => ['$toString' => '$project_type_id'], 'project_name' => 1, 'is_closed' => 1, 'responsibility' => 1, 'project_type' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'start_date' => 1, 'end_date' => 1, 'issues' => 1]],
                ['$match' => ['issues' => []]],
                ['$project' => [
                    '_id' => 0, 'is_closed' => 1, 'project_id' => '$project_id', 'creator_id' => '$creator_id', 'project_type_id' => '$project_type_id', 'project_type' => '$project_type', 'project_name' => '$project_name', 'customer_name' => '$customer_name', 'job_order' => '$job_order', 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    'updated_at' => ['$dateToString' => ['date' => '$updated_at', 'format' => '%Y-%m-%d %H:%M:%S']], 'is_approved_null' => null, 'is_approved_true' => null, 'is_approved_false' => null, 'progress' => null, 'responsibility' => 1, 'end_date' => 1, 'start_date' => 1
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'progress' => 1, 'responsibility' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'end_date' => 1, 'start_date' => 1]],
                ['$lookup' => ['from' => 'Approved', 'localField' => 'project_id', 'foreignField' => 'project_id', 'as' => 'result_Approved', 'pipeline' => [['$project' => ['_id' => 0, 'verification_type' => 1, 'is_validated' => 1, 'validated_at' => 1, 'project_id' => 1, 'createdApproved_at' => '$created_at']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Approved', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'progress' => 1, 'result_Approved' => 1, 'end_date' => 1, 'start_date' => 1]],
                ['$project' => [
                    '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'version' => '$traceability.version', 'progress' => 1, 'end_date' => 1, 'start_date' => 1,
                    'result_Approved' => ['$map' => ['input' => '$result_Approved', 'as' => 'respp', 'in' => ['project_id' => ['$toString' => '$$respp.project_id'], 'verification_type' => '$$respp.verification_type', 'is_validated' => '$$respp.is_validated', 'createdApproved_at' => '$$respp.createdApproved_at']]]
                ]],
                ['$project' => [
                    '_id' => 0, 'end_date' => 1, 'start_date' => 1, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'progress' => 1,
                    'result_Approved' => ['$filter' => ['input' => '$result_Approved', 'as' => 'approved', 'cond' => ['$eq' => ['$$approved.verification_type', 'PROJECT_PLAN']]]]
                ]],
                ['$unwind' => '$result_Approved'],
                ['$project' => [
                    '_id' => 0, 'project_id' => ['$toString' => '$project_id'], 'creator_id' => 1, 'teamspace_id' => 1, 'project_type_id' => 1, 'project_type' => 1, 'project_name' => 1, 'customer_name' => 1, 'created_at' => 1, 'updated_at' => 1, 'job_order' => 1, 'is_closed' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'progress' => 1, 'responsibility' => 1, 'end_date' => 1, 'start_date' => 1, 'end_date' => 1, 'start_date' => 1, 'progress' => 1,
                    'verification_type' => '$result_Approved.verification_type', 'is_validated' => '$result_Approved.is_validated', 'createdApproved_at' => '$result_Approved.createdApproved_at'
                ]],
                ['$group' => [
                    '_id' => '$project_id', 'project_name' => ['$last' => '$project_name'], 'project_id' => ['$last' => '$project_id'], 'is_closed' => ['$last' => '$is_closed'], 'responsibility' => ['$last' => '$responsibility'], 'creator_id' => ['$last' => '$creator_id'], 'project_type_id' => ['$last' => '$project_type_id'], 'project_type' => ['$last' => '$project_type'], 'customer_name' => ['$last' => '$customer_name'], 'job_order' => ['$last' => '$job_order'], 'created_at' => ['$last' => '$created_at'], 'updated_at' => ['$last' => '$updated_at'],
                    'is_approved_null' => ['$last' => '$is_approved_null'], 'is_approved_true' => ['$last' => '$is_approved_true'], 'is_approved_false' => ['$last' => '$is_approved_false'], 'verification_type' => ['$last' => '$verification_type'], 'is_validated' => ['$last' => '$is_validated'], 'start_date' => ['$last' => '$start_date'], 'end_date' => ['$last' => '$end_date']
                ]],
                ['$project' => ['_id' => 0, 'project_id' => 1, 'project_name' => 1, 'project_type' => 1, 'is_closed' => 1, 'responsibility' => 1, 'creator_id' => 1, 'project_type_id' => 1, 'prject_type' => 1, 'customer_name' => 1, 'job_order' => 1, 'created_at' => 1, 'updated_at' => 1, 'is_approved_null' => 1, 'is_approved_true' => 1, 'is_approved_false' => 1, 'is_validated' => 1, 'end_date' => 1, 'start_date' => 1]]
            ];



            $result1 = $this->db->selectCollection('Projects')->aggregate($pipelineCalculateMainIssuesOnly);

            $data1 = array();
            foreach ($result1 as $doc) \array_push($data1, $doc);

            $result2 = $this->db->selectCollection('Projects')->aggregate($pipelineNonIssues);

            $data2 = array();
            foreach ($result2 as $doc) \array_push($data2, $doc);

            // return response()->json($data2);



            $data = array_merge($data1, $data2);

            return response()->json([
                "status" => "success",
                "message" => "Get all project in system successfully !!",
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

    //* [GET] /project/projects-all
    public function projectsAll(Request $request)
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

            $pipelineNew = [
                ['$project' => ['_id' => 1, 'project_id' => ['$toString' => '$_id'], 'creator_id' => 1, 'customer_name' => 1, 'project_type' => 1, 'project_name' => 1, 'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']]]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'result_Accounts', 'pipeline' => [['$project' => ['_id' => 0, 'email' => '$username', 'creator_name' => '$name_en']]]]],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_Accounts', 0]], '$$ROOT']]]],
                ['$lookup' => ['from' => 'StatementOfWork', 'localField' => '_id', 'foreignField' => 'project_id', 'as' => 'result_StatementOfWork']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$result_StatementOfWork', 0]], '$$ROOT']]]],
                ['$project' => ['_id' => 0, 'creator_name' => 1, 'email' => 1, 'project_name' => 1, 'project_type' => 1, 'project_id' => 1, 'customer_name' => 1, 'start_date' => 1, 'end_date' => 1,]]
            ];

            $result = $this->db->selectCollection('Projects')->aggregate($pipelineNew);

            $data = array();
            foreach ($result as $doc) \array_push($data, $doc);

            return response()->json([
                "status" => "success",
                "message" => "Get all project in system successfully !!",
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


    //* [GET] /statement-of-work/list
    public function statementList(Request $request)
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
                        'localField' => 'creator_id',
                        'foreignField' => '_id',
                        'as' => 'Accounts'
                    ]
                ],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],

                [
                    '$project' => [
                        '_id' => 0,
                        'statement_of_work_id'      => ['$toString' => '$_id'],
                        'creator_id'                => ['$toString' => '$creator_id'],
                        'project_id'                => ['$toString' => '$project_id'],
                        'teamspace_id'          => ['$toString' => '$teamspace_id'],
                        'project_type'          => 1,
                        'project_name'          => 1,
                        'customer_name'         => 1,
                        'version'               => 1,
                        'customer_contact'      => 1,
                        'sap_code'                  => 1,
                        'introduction_of_project'   => 1,
                        'list_of_introduction'      => 1,
                        'cost_estimation'           => 1,
                        'scope_of_project'          => 1,
                        'objective_of_project'      => 1,
                        'start_date'                => 1,
                        'end_date'                  => 1,
                        // 'is_verified'               => 1,
                        // 'verified_id'               => ['$toString' => '$verified_id'],   //! approved_by
                        // 'verified_date'             => 1,
                        'create_date'               => 1,
                        'created_at' => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                    ]
                ],
            ];

            $result = $this->db->selectCollection('StatementOfWork')->aggregate($pipeline);

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

    //* [POST] /statement-of-work/new
    public function newStatement(Request $request)
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
                'teamspace_id'              => 'nullable | string',
                'project_type'              => 'nullable | string ',
                'project_name'              => 'nullable | string ',
                'customer_name'             => 'nullable | string ',
                'customer_contact'          => 'nullable | array',
                'scope_of_project'          => 'nullable | array',
                'introduction_of_project'   => 'nullable | string ',
                'list_of_introduction'      => 'nullable | array',
                'objective_of_project'      => 'nullable | array',
                'cost_estimation'           => 'nullable | array',
                // 'sap_code'                  => 'nullable | string',
                'start_date'                => 'nullable | string ',
                'end_date'                  => 'nullable | string ',
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

            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);

            $decoded                = $jwt->decoded;
            $teamSpaceID            = $request->teamspace_id;
            $projectType            = $request->project_type;
            $projectName            = $request->project_name;
            $customerName           = $request->customer_name;
            $customerContact        = $request->customer_contact;
            $costEstimation         = $request->cost_estimation;
            $introduction           = $request->introduction_of_project;
            $listIntroduction       = $request->list_of_introduction;
            $scopes                 = $request->scope_of_project;
            $objectives             = $request->objective_of_project;
            $startDate              = $request->start_date;
            $endDate                = $request->end_date;
            // $sapCode                = $request->sap_code;
            $createBy               = $decoded->creater_by;
            $createDate             = date('Y-m-d');

            // if document has been created, cannot create again
            $checkDoc = $this->db->selectCollection("StatementOfWork")->findOne(['project_id' => $this->MongoDBObjectId($projectName)]);
            if ($checkDoc !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This project has been created',
                    "data" => [],
                ], 400);
            }

            //! check data
            $filter1 = ["_id" => $this->MongoDBObjectId($teamSpaceID)];
            $options1 = [
                "limit" => 1,
                "projection" => [
                    "_id" => 0, "creator_id" => ['$toString' => '$creator_id'],
                    "members" => 1, "description" => 1,
                ]
            ];
            $chkID      = $this->db->selectCollection("Teamspaces")->find($filter1, $options1);
            $dataChkID = array();
            foreach ($chkID as $doc) \array_push($dataChkID, $doc);
            if (\count($dataChkID) == 0) {
                return response()->json(["status" => "error", "message" => "Teamspace not found", "data" => []], 400);
            }

            $filter2 = ['project_type' => $projectType];
            $options2 = [
                'limit' => 1,
                'projection' => [
                    "_id" => 0, "project_type_id" => ['$toString' => '$_id'],
                    "project_type" => 1, "work_center_issue" => 1, "cost_estimation" => 1, 'description' => 1,
                ]
            ];

            $chkProjectTypeID = $this->db->selectCollection("ProjectTypeSetting")->find($filter2, $options2);
            $dataChk2 = array();
            foreach ($chkProjectTypeID as $doc) \array_push($dataChk2, $doc);
            if (\count($dataChk2) == 0) {
                return response()->json(["status" => "error", "message" => "Product type not found", "data" => []], 400);
            }

            //! check data
            $projectTypeID = $dataChk2[0]->project_type_id;
            $sapCode = $dataChk2[0]->description;

            // return response()->json($sapCode);

            $this->db->selectCollection("Projects")->insertOne([
                "creator_id"                => $this->MongoDBObjectId($createBy),
                "teamspace_id"              => $this->MongoDBObjectId($teamSpaceID),
                "project_type_id"           => $this->MongoDBObjectId($projectTypeID),
                "project_type"              => $projectType,
                "project_name"              => $projectName,
                "customer_name"             => $customerName,
                "is_closed"                 => false,
                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,
            ]);

            //! Query project id from Projects collection
            $filter = ["project_name" => $projectName, "customer_name" => $customerName];
            $options = [
                '$sort' => ['created_at' => -1],
                "limit" => 1,
                "projection" => [
                    "_id" => 0, "project_id" => ['$toString' => '$_id'],
                    "project_name" => 1, "teamspace_id" => ['$toString' => '$teamspace_id'],
                ]
            ];

            $queryProjectID = $this->db->selectCollection("Projects")->find($filter, $options);
            $dataChkID = array();
            foreach ($queryProjectID as $doc) \array_push($dataChkID, $doc);

            // return response()->json($dataChkID);

            if (\count($dataChkID) == 0) {
                return response()->json(["status" => "error", "message" => "Project name dose not exist", "data" => []], 400);
            }

            $projectID = $dataChkID[0]->project_id;

            // return response()->json($projectID);

            $document = array(
                "project_id"                => $this->MongoDBObjectId($projectID),
                "creator_id"                => $this->MongoDBObjectId($createBy),
                "teamspace_id"              => $this->MongoDBObjectId($teamSpaceID),
                "project_type"              => $projectType,
                "project_name"              => $projectName,
                "customer_name"             => $customerName,
                "version"                   => "0.01",
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
                "status"                    => null,
                "create_date"               => $createDate,
                "created_at"                => $timestamp,
                "updated_at"                => $timestamp,

            );

            $result = $this->db->selectCollection("StatementOfWork")->insertOne($document);

            if ($result->getInsertedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Add new statement of work failed",
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

    //* [PUT] /statement-of-work/edit
    public function editStatement(Request $request)
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
                'statement_of_work_id'      => 'required | string',
                'project_type'              => 'required | string |min:1|max:100',
                'project_name'              => 'required | string |min:1|max:255',
                'customer_name'             => 'required | string |min:1|max:255',
                'customer_contact'          => 'required | array',
                'cost_estimation'           => 'required | array',
                'sap_code'                  => 'nullable | string',
                'introduction_of_project'   => 'required | string |min:1|max:1000',
                'list_of_introduction'      => 'required | array',
                'scope_of_project'          => 'required | array',
                'objective_of_project'      => 'required | array',
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

            // $timestamp = $this->MongoDBUTCDatetime(time()*1000);
            $statementOfWorkID      = $request->statement_of_work_id;
            $projectType            = $request->project_type;
            $projectName            = $request->project_name;
            $customerName           = $request->customer_name;
            $customerContact        = $request->customer_contact;
            $costEstimation         = $request->cost_estimation;
            $sapCode                = $request->sap_code;
            $introduction           = $request->introduction_of_project;
            $listIntroduction       = $request->list_of_introduction;
            $scopes                 = $request->scope_of_project;
            $objectives             = $request->objective_of_project;
            $startDate              = $request->start_date;
            $endDate                = $request->end_date;

            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($statementOfWorkID)]],
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
            $checkEdit = $this->db->selectCollection("StatementOfWork")->aggregate($pipline);
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

            // If is_edit is fasle, cannot edit
            if ($checkEditData[0]->is_edit === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot edit this document',
                    "data" => [],
                ], 400);
            }

            // if is_edit is not false, can edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status === null) {
                $updateDocument = $this->db->selectCollection("StatementOfWork")->updateOne(
                    ['_id' => $this->MongoDBObjectId($statementOfWorkID)],
                    ['$set' => [
                        'project_type'              => $projectType,
                        'project_name'              => $projectName,
                        'customer_name'             => $customerName,
                        'customer_contact'          => $customerContact,
                        'cost_estimation'           => $costEstimation,
                        'sap_code'                  => $sapCode,
                        'introduction_of_project'   => $introduction,
                        'list_of_introduction'      => $listIntroduction,
                        'scope_of_project'          => $scopes,
                        'objective_of_project'      => $objectives,
                        'start_date'                => $startDate,
                        'end_date'                  => $endDate,
                        "updated_at"                => $timestamp
                    ]]
                );
                $updateProjectName = $this->db->selectCollection("Projects")->updateOne(
                    ['_id' => $this->MongoDBObjectId($checkEditData[0]->project_id)],
                    ['$set' => [
                        'project_name'  => $projectName,
                    ]]
                );
            }

            // if assessed, but need to edit
            if ($checkEditData[0]->is_edit !== false && $checkEditData[0]->status !== null) {
                $option = [
                    "project_id"                => $checkEditData[0]->project_id,
                    "creator_id"                => $checkEditData[0]->creator_id,
                    'project_type'              => $projectType,
                    'project_name'              => $projectName,
                    'customer_name'             => $customerName,
                    'customer_contact'          => $customerContact,
                    'cost_estimation'           => $costEstimation,
                    'sap_code'                  => $sapCode,
                    'introduction_of_project'   => $introduction,
                    'list_of_introduction'      => $listIntroduction,
                    'scope_of_project'          => $scopes,
                    'objective_of_project'      => $objectives,
                    'start_date'                => $startDate,
                    'end_date'                  => $endDate,
                    "version"                   => $checkEditData[0]->version . "_edit",
                    "is_edit"                   => true,
                    "status"                    => null,
                    "created_at"                => $timestamp,
                    "updated_at"                => $timestamp,
                ];
                $setEditFalse = $this->db->selectCollection("StatementOfWork")->updateOne(
                    ['_id' => $this->MongoDBObjectId($statementOfWorkID)],
                    ['$set' => [
                        "is_edit" => false,
                    ]]
                );
                $updateProjectName = $this->db->selectCollection("Projects")->updateOne(
                    ['_id' => $this->MongoDBObjectId($checkEditData[0]->project_id)],
                    ['$set' => [
                        'project_name'  => $projectName,
                    ]]
                );
                $insertForEditApproved = $this->db->selectCollection("StatementOfWork")->insertOne($option);
            }

            return response()->json([
                "status" => "success",
                "message" => "Edit statement of work detail successfully !!",
                "data" => []
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    //* [DELETE] /statement-of-work/delete
    // public function deleteStatement(Request $request)
    // {
    //     try {
    //     //! JWT
    //     $header = $request->header('Authorization');
    //     $jwt = $this->jwtUtils->verifyToken($header);
    //     if (!$jwt->state) return response()->json([
    //         "status" => "error",
    //         "message" => "Unauthorized",
    //         "data" => [],
    //     ], 401);

    //     $rules = [
    //             'statement_of_work_id' => 'required | string',
    //         ];
    //     $validators = Validator::make($request->all(), $rules);

    //     if ($validators -> fails()) {
    //         return response()->json([
    //             "status" => "error",
    //             "state" => false,
    //             "message" => "Bad request",
    //             "data" => [
    //                 [
    //                     "validator" => $validators -> errors()
    //                 ]
    //             ]
    //         ], 400);
    //     }

    //     $statementOfWorkID = $request ->statement_of_work_id;

    //     //! check data
    //         $filter = ["_id" => $this->MongoDBObjectId($statementOfWorkID)];
    //         $options = [
    //             "limit" => 1,
    //             "projection" => [
    //                     "_id" => 0,
    //                     "statement_of_work_id" => ['$toString' => '$_id'],
    //                     "project_id" => ['$toString' => '$project_id'],
    //                     "teamspace_id" => ['$toString' => '$teamspace_id'],
    //                 ]
    //             ];
    //         $result = $this->db->selectCollection("StatementOfWork")->find($filter, $options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         if (\count($data) == 0)
    //         return response()->json([
    //                 "status" =>  "error",
    //                 "message" => "statement of work id not found",
    //                 "data" => [],
    //             ],500);
    //      //! check data


    //     $result = $this->db->selectCollection("StatementOfWork")->deleteOne($filter);

    //     if ($result->getDeletedCount() == 0)
    //         return response()->json(["status" => "error", "message" => "There has been no data deletion", "data" =>[],],500);


    //     return response()->json([
    //         "status" => "success",
    //         "message" => "Delete statement of work successfully",
    //         "data" => [],
    //     ],200);

    //     } catch(\Exception $e){
    //         return response()->json([
    //             "status" => "error",
    //             "message" => $e->getMessage(),
    //             "data" => [],
    //         ],500);
    //     }
    // }

    //* [PUT] /statement-of-work/update-scopes
    // public function updateScopes(Request $request)
    // {
    //     try {
    //         //! JWT
    //             $header = $request->header('Authorization');
    //             $jwt = $this->jwtUtils->verifyToken($header);
    //             if (!$jwt->state) return response()->json([
    //                 "status" => "error",
    //                 "message" => "Unauthorized",
    //                 "data" => [],
    //             ], 401);

    //         $rules = [
    //             'statement_of_work_id' => 'required | string',
    //             'scope_of_project' => 'required | array',
    //         ];

    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators -> fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "state" => false,
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators -> errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }
    //         $decoded = $jwt->decoded;

    //         $statementOfWorkID          = $request-> statement_of_work_id;
    //         $scopes             = $request -> scope_of_project;

    //         //! check data
    //         $filter = ["_id" => $this->MongoDBObjectId($statementOfWorkID)];
    //         $options = [
    //             "limit" => 1,
    //             "projection" => [
    //                     "_id" => 0,
    //                     "statement_of_work_id" => ['$toString' => '$_id'],
    //                     "project_id" => ['$toString' => '$project_id'],
    //                     "teamspace_id" => ['$toString' => '$teamspace_id'],
    //                 ]
    //             ];
    //         $result = $this->db->selectCollection("StatementOfWork")->find($filter, $options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         if (\count($data) == 0)
    //         return response()->json([
    //                 "status" =>  "error",
    //                 "message" => "statement of work id not found",
    //                 "data" => [],
    //             ],500);
    //         //! check data

    // \date_default_timezone_set('Asia/Bangkok');
    // $date = date('Y-m-d H:i:s');
    // $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp()+2.52e4)*1000);
    //// $timestamp = $this->MongoDBUTCDatetime(time()*1000);

    //         $update = ["scope_of_project" => $scopes,"updated_at" => $timestamp];

    //         $result = $this->db->selectCollection("StatementOfWork")->updateOne($filter, ['$set' => $update]);

    //         if ($result->getModifiedCount() == 0)
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "There has been no data modification",
    //                 "data" => []
    //             ],500);

    //         return response() -> json([
    //             "status" => "success",
    //             "message" => "ํYou update scopes successfully !!",
    //             "data" => [$update]
    //         ],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }

    //* [PUT] /statement-of-work/update-objectives
    // public function updateObjectives(Request $request)
    // {
    //     try {
    //         //! JWT
    //             $header = $request->header('Authorization');
    //             $jwt = $this->jwtUtils->verifyToken($header);
    //             if (!$jwt->state) return response()->json([
    //                 "status" => "error",
    //                 "message" => "Unauthorized",
    //                 "data" => [],
    //             ], 401);

    //             $rules = [
    //                 'statement_of_work_id' => 'required | string',
    //                 'objective_of_project' => 'required | array',
    //             ];


    //         $validators = Validator::make($request->all(), $rules);

    //         if ($validators -> fails()) {
    //             return response()->json([
    //                 "status" => "error",
    //                 "state" => false,
    //                 "message" => "Bad request",
    //                 "data" => [
    //                     [
    //                         "validator" => $validators -> errors()
    //                     ]
    //                 ]
    //             ], 400);
    //         }
    //         $decoded = $jwt->decoded;

    //         $statementOfWorkID          = $request-> statement_of_work_id;
    //         $objectives                 = $request -> objective_of_project;

    // \date_default_timezone_set('Asia/Bangkok');
    // $date = date('Y-m-d H:i:s');
    // $timestamp = $this->MongoDBUTCDatetime(((new \DateTime($date))->getTimestamp()+2.52e4)*1000);

    // // $timestamp = $this->MongoDBUTCDatetime(time()*1000);

    //         //! check data
    //         $filter = ["_id" => $this->MongoDBObjectId($statementOfWorkID)];
    //         $options = [
    //             "limit" => 1,
    //             "projection" => [
    //                     "_id" => 0,
    //                     "statement_of_work_id" => ['$toString' => '$_id'],
    //                     "project_id" => ['$toString' => '$project_id'],
    //                     "teamspace_id" => ['$toString' => '$teamspace_id'],
    //                 ]
    //             ];
    //         $result = $this->db->selectCollection("StatementOfWork")->find($filter, $options);
    //         $data = array();
    //         foreach ($result as $doc) \array_push($data, $doc);

    //         if (\count($data) == 0)
    //         return response()->json([
    //                 "status" =>  "error",
    //                 "message" => "statement of work id not found",
    //                 "data" => [],
    //             ],500);
    //         //! check data

    //         $filter = ["_id" => $this->MongoDBObjectId($statementOfWorkID)];
    //         $update = ["objective_of_project" => $objectives,"updated_at" => $timestamp];

    //         $result = $this->db->selectCollection("StatementOfWork")->updateOne($filter, ['$set' => $update]);

    //         if ($result->getModifiedCount() == 0)
    //             return response()->json([
    //                 "status" => "error",
    //                 "message" => "There has been no data modification",
    //                 "data" => []
    //             ]);

    //         return response() -> json([
    //             "status" => "success",
    //             "message" => "ํYou update Objectives successfully !!",
    //             "data" => [$update]
    //         ],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }

    // //* [POST] /statement-of-work/verified
    // public function verifiedStatement2(Request $request)
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

    //         $rules = [
    //                 'project_id'          => 'required | string | min:1 | max:255',
    //                 "approved_by"         => ['nullable' , 'string' ],
    //                 "is_approved"         => ['required' , 'boolean' ],
    //             ];

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

    //         $projectID  = $request->project_id;

    //         //! check data
    //             $filter = ["_id" => $this->MongoDBObjectId($projectID)];
    //             $options = ["limit" => 1,"projection" => ["_id" => 0,"project_id" => ['$toString' => '$_id'],"is_approved"=>1,]];

    //             $chkProjectID = $this->db->selectCollection("StatementOfWork")->find($filter, $options);

    //             $dataChk = array();
    //             foreach ($chkProjectID as $doc) \array_push($dataChk, $doc);

    //             if (\count($dataChk) == 0)
    //             return response()->json(["status" => "error", "message" => "Project id not found" , "data"=> []],200);
    //         //! check data

    //         $isApproved     = $request->is_approved;

    //         $update = [
    //                 "is_approved"               => $isApproved,
    //                 "approved_by"               => $this->MongoDBObjectId($decoded->creater_by),
    //                 "approved_date"             => date('Y-m-d'),
    //                 "is_approved_at"            => $timestamp,
    //             ];
    //         $result = $this->db->selectCollection("StatementOfWork")->updateOne($filter, ['$set' => $update]);

    //         if ($result->getModifiedCount() == 0)
    //         return response()->json([
    //             "status" => "error",
    //             "message" => "There has been no data modification",
    //             "data" => []
    //         ],500);

    //         return response() -> json([
    //             "status" => "success",
    //             "message" => "Approved statement of work successfully !!",
    //             "data" => [$result]
    //         ],200);

    //     } catch(\Exception $e){
    //         $statusCode = $e->getCode() ?: 500;
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             "data" => [],
    //         ], $statusCode);
    //     }
    // }



    //* [GET] /statement-of-work/get-doc // Get ducuments for each project_id
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
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    // "project_name" => 1,
                    // "version" => 1,
                    // "is_edit" => 1,
                    // "status" => 1,
                    // "creator_id" => ['$toString' => '$creator_id'],
                    "name_en" => 1,
                    "customer_name" => 1,
                    // "project_type" => 1,
                    "created_at" => ['$dateToString' => ['date' => '$created_at', 'format' => '%Y-%m-%d %H:%M:%S']],
                ]],
                [
                    '$group' => [
                        '_id' => ['project_id' => '$project_id', 'customer_name' => '$customer_name', 'name_en' => '$name_en'],
                        'created_at' => ['$last' => '$created_at'], "document_id" => ['$last' => '$_id']
                    ]
                ],
                ['$project' => [
                    "_id" => 0,
                    "project_id" => '$_id.project_id',
                    // "project_name" => '$_id.project_name',
                    // "statement_of_work_id" => '$document_id',
                    // "status" => 1,
                    "customer_name" => '$_id.customer_name',
                    // "project_type" => '$_id.project_type',
                    // "version" => 1,
                    "created_at" => 1,
                    // "is_edit" => 1,
                    // "creator_id" => '$_id.creator_id',
                    "creator_name" => '$_id.name_en'
                ]],
            ];

            $userDoc = $this->db->selectCollection("StatementOfWork")->aggregate($pipline);
            $dataUserDoc = array();
            // foreach ($userDoc as $doc) \array_push($dataUserDoc, $doc);
            foreach ($userDoc as $doc) {
                $pipline = [
                    ['$match' => ['project_id' => $this->MongoDBObjectId($doc->project_id)]],
                    // ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                    // ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                    [
                        '$project' => [
                            'version' => 1, 'statement_of_work_id' => ['$toString' => '$_id'],
                            'project_name' => 1, 'status' => 1, 'is_edit' => 1, "start_date" => 1, "end_date" => 1,
                            'project_type' => 1
                        ]
                    ]
                ];
                $allVersion = $this->db->selectCollection("StatementOfWork")->aggregate($pipline);
                $versionsAll = array();
                foreach ($allVersion as $ver) {
                    $version = $ver->version;
                    $statementOfWorkID = $ver->statement_of_work_id;
                    $projectName = $ver->project_name;
                    $status = $ver->status;
                    $isEdit = $ver->is_edit;
                    $startDate = $ver->start_date;
                    $endDate = $ver->end_date;
                    $projectType = $ver->project_type;
                    array_push($versionsAll, [
                        "version" => $version, "statement_of_work_id" => $statementOfWorkID,
                        "project_name" => $projectName, "status" => $status, "is_edit" => $isEdit,
                        "start_date" => $startDate, "end_date" => $endDate, "project_type" => $projectType
                    ]);
                }
                $versions = ["version_all" => $versionsAll];

                $data = array_merge((array)$doc, $versions);
                array_push($dataUserDoc, $data);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Get all Statement of Work successfully !!',
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


    //* [POST] /statement-of-work/get-individual-doc
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
                'statement_of_work_id'       => 'required | string | min:1 | max:255',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    "data" => [],
                ], 400);
            }

            $statementOfWorkID = $request->statement_of_work_id;
            $pipline = [
                ['$match' => ['_id' => $this->MongoDBObjectId($statementOfWorkID)]],
                ['$lookup' => ['from' => 'Accounts', 'localField' => 'creator_id', 'foreignField' => 'user_id', 'as' => 'Accounts']],
                ['$replaceRoot' => ['newRoot' => ['$mergeObjects' => [['$arrayElemAt' => ['$Accounts', 0]], '$$ROOT']]]],
                ['$project' => [
                    "_id" => 0,
                    "statement_of_work_id" => ['$toString' => '$_id'],
                    "project_id" => ['$toString' => '$project_id'],
                    "creator_id" => ['$toString' => '$creator_id'],
                    "creator_name" => '$name_en',
                    "project_type" => 1,
                    "project_name" => 1,
                    "customer_name" => 1,
                    "version" => 1,
                    "customer_contact" => 1,
                    "cost_estimation" => 1,
                    "sap_code" => 1,
                    "introduction_of_project" => 1,
                    "list_of_introduction" => 1,
                    "scope_of_project" => 1,
                    "objective_of_project" => 1,
                    "start_date" => 1,
                    "end_date" => 1,
                    "create_date" => 1,
                ]]
            ];
            $userDoc = $this->db->selectCollection("StatementOfWork")->aggregate($pipline);
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
            $userCov = $this->db->selectCollection("StatementOfWork")->aggregate($cover);
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


    //* [POST] /project/close-project
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

            $rules = [
                'project_id'              => 'required | string',
                'is_closed'              => 'required | boolean',

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


            $projectID            = $request->project_id;
            $isClosed           = $request->is_closed;


            // if document has been created, cannot create again
            $checkDoc = $this->db->selectCollection("Projects")->find(['_id' => $this->MongoDBObjectId($projectID)]);

            $dataProject = [];
            foreach ($checkDoc as $doc) \array_push($dataProject, $doc);


            //! check data

            $update = [
                "is_closed"                 => $isClosed,
                "updated_at"                => $timestamp,
            ];

            $result = $this->db->selectCollection("Projects")->updateOne(['_id' => $this->MongoDBObjectId($projectID)], ['$set' => $update]);



            if ($result->getModifiedCount() == 0)
                return response()->json([
                    "status" => "error",
                    "message" => "Close project failed",
                    "data" => []
                ], 500);

            return response()->json([
                "status" => "success",
                "message" => "Close project successfully !!",
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
}
