<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

use ILIAS\Refinery\Transformation;
use ILIAS\Refinery\Random\Seed\GivenSeed;
use ILIAS\Refinery\Random\Group as RandomGroup;

require_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
* Service GUI class for tests. This class is the parent class for all
* service classes which are called from ilObjTestGUI. This is mainly
* done to reduce the size of ilObjTestGUI to put command service functions
* into classes that could be called by ilCtrl.
*
* @ilCtrl_IsCalledBy ilTestServiceGUI: ilObjTestGUI
*
* @author	Helmut Schottmüller <helmut.schottmueller@mac.com>
* @author	Björn Heyser <bheyser@databay.de>
* @version	$Id$
*
* @ingroup ModulesTest
*/
class ilTestServiceGUI
{
    protected \ILIAS\Test\InternalRequestService $testrequest;

    public ?ilObjTest $object = null;
    public ?ilTestService $service = null;
    protected ilDBInterface $db;
    public ilLanguage $lng;
    /**
     * sk 2023-08-01: We need this union type, even if it is wrong! To change this
     * @todo we have to fix the rendering of the feedback modal in
     * `ilTestPlayerAbstractGUI::populateIntantResponseModal()`.
     */
    public ilGlobalTemplateInterface|ilTemplate $tpl;
    public ilCtrl $ctrl;
    protected ilTabsGUI $tabs;
    protected ilObjectDataCache $objCache;
    protected ilComponentRepository $component_repository;
    protected ilObjUser $user;

    public $ilias;
    public $tree;
    public $ref_id;

    /**
     * factory for test session
     *
     * @var ilTestSessionFactory
     */
    protected $testSessionFactory = null;

    /**
     * factory for test sequence
     *
     * @var ilTestSequenceFactory
     */
    protected $testSequenceFactory = null;

    /**
     * @var ilTestParticipantData
     */
    protected $participantData;

    private RandomGroup $randomGroup;

    /**
     * @var ilTestObjectiveOrientedContainer
     */
    private $objectiveOrientedContainer;

    private $contextResultPresentation = true;

    /**
     * @return boolean
     */
    public function isContextResultPresentation(): bool
    {
        return $this->contextResultPresentation;
    }

    /**
     * @param boolean $contextResultPresentation
     */
    public function setContextResultPresentation($contextResultPresentation)
    {
        $this->contextResultPresentation = $contextResultPresentation;
    }

    /**
     * The constructor takes the test object reference as parameter
     *
     * @param object $a_object Associated ilObjTest class
     * @access public
     */
    public function __construct(ilObjTest $a_object)
    {
        global $DIC;
        $lng = $DIC['lng'];
        $tpl = $DIC['tpl'];
        $ilCtrl = $DIC['ilCtrl'];
        $user = $DIC->user();
        $ilias = $DIC['ilias'];
        $tree = $DIC['tree'];
        $ilDB = $DIC['ilDB'];
        $component_repository = $DIC['component.repository'];
        $ilTabs = $DIC['ilTabs'];
        $ilObjDataCache = $DIC['ilObjDataCache'];

        $lng->loadLanguageModule('cert');

        $this->db = $ilDB;
        $this->lng = &$lng;
        $this->tpl = &$tpl;
        $this->ctrl = &$ilCtrl;
        $this->user = &$user;
        $this->tabs = $ilTabs;
        $this->component_repository = $component_repository;
        $this->objCache = $ilObjDataCache;
        $this->ilias = &$ilias;
        $this->object = &$a_object;
        $this->tree = &$tree;
        $this->ref_id = $a_object->getRefId();

        $this->service = new ilTestService($a_object);
        $this->testrequest = $DIC->test()->internal()->request();
        $this->testSessionFactory = new ilTestSessionFactory($this->object);

        $this->testSequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $component_repository, $this->object);
        $this->randomGroup = $DIC->refinery()->random();
        $this->objectiveOrientedContainer = null;
    }

    public function setParticipantData(ilTestParticipantData $participantData): void
    {
        $this->participantData = $participantData;
    }

    public function getParticipantData(): ilTestParticipantData
    {
        return $this->participantData;
    }

    /**
     * This method uses the data of a given test pass to create an evaluation for displaying into a table used in the ilTestEvaluationGUI
     *
     * @param ilTestSession $testSession the current test session
     * @param array $passes An integer array of test runs
     * @param boolean $withResults $withResults tells the method to include all scoring data into the  returned row
     * @return array The array contains the date of the requested row
     */
    public function getPassOverviewTableData(ilTestSession $testSession, $passes, $withResults): array
    {
        $data = array();

        if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
            $considerHiddenQuestions = false;

            $objectivesAdapter = ilLOTestQuestionAdapter::getInstance($testSession);
        } else {
            $considerHiddenQuestions = true;
        }

        $scoredPass = $this->object->_getResultPass($testSession->getActiveId());

        $questionHintRequestRegister = ilAssQuestionHintTracking::getRequestRequestStatisticDataRegisterByActiveId(
            $testSession->getActiveId()
        );

        foreach ($passes as $pass) {
            $row = [
                'scored' => false,
                'pass' => $pass,
                'date' => ilObjTest::lookupLastTestPassAccess($testSession->getActiveId(), $pass)
            ];
            $considerOptionalQuestions = true;

            if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
                $testSequence = $this->testSequenceFactory->getSequenceByActiveIdAndPass($testSession->getActiveId(), $pass);
                $testSequence->loadFromDb();
                $testSequence->loadQuestions();

                if ($this->object->isRandomTest() && !$testSequence->isAnsweringOptionalQuestionsConfirmed()) {
                    $considerOptionalQuestions = false;
                }

                $testSequence->setConsiderHiddenQuestionsEnabled($considerHiddenQuestions);
                $testSequence->setConsiderOptionalQuestionsEnabled($considerOptionalQuestions);

                $objectivesList = $this->buildQuestionRelatedObjectivesList($objectivesAdapter, $testSequence);
                $objectivesList->loadObjectivesTitles();

                $row['objectives'] = $objectivesList->getUniqueObjectivesStringForQuestions($testSequence->getUserSequenceQuestions());
            }

            if ($withResults) {
                $result_array = $this->object->getTestResult($testSession->getActiveId(), $pass, false, $considerHiddenQuestions, $considerOptionalQuestions);

                foreach ($result_array as $resultStructKEY => $question) {
                    if ($resultStructKEY === 'test' || $resultStructKEY === 'pass') {
                        continue;
                    }

                    $requestData = $questionHintRequestRegister->getRequestByTestPassIndexAndQuestionId($pass, $question['qid']);

                    if ($requestData instanceof ilAssQuestionHintRequestStatisticData && $result_array[$resultStructKEY]['requested_hints'] === null) {
                        $result_array['pass']['total_requested_hints'] += $requestData->getRequestsCount();

                        $result_array[$resultStructKEY]['requested_hints'] = $requestData->getRequestsCount();
                        $result_array[$resultStructKEY]['hint_points'] = $requestData->getRequestsPoints();
                    }
                }

                if (!$result_array['pass']['total_max_points']) {
                    $row['percentage'] = 0;
                } else {
                    $row['percentage'] = ($result_array['pass']['total_reached_points'] / $result_array['pass']['total_max_points']) * 100;
                }

                $row['max_points'] = $result_array['pass']['total_max_points'];
                $row['reached_points'] = $result_array['pass']['total_reached_points'];
                $row['scored'] = ($pass == $scoredPass);
                $row['num_workedthrough_questions'] = $result_array['pass']['num_workedthrough'];
                $row['num_questions_total'] = $result_array['pass']['num_questions_total'];

                if ($this->object->isOfferingQuestionHintsEnabled()) {
                    $row['hints'] = $result_array['pass']['total_requested_hints'];
                }
            }

            $data[] = $row;
        }

        return $data;
    }

    /**
     * @param ilTestObjectiveOrientedContainer $objectiveOrientedContainer
     */
    public function setObjectiveOrientedContainer(ilTestObjectiveOrientedContainer $objectiveOrientedContainer)
    {
        $this->objectiveOrientedContainer = $objectiveOrientedContainer;
    }

    /**
     * @return ilTestObjectiveOrientedContainer
     */
    public function getObjectiveOrientedContainer(): ?ilTestObjectiveOrientedContainer
    {
        return $this->objectiveOrientedContainer;
    }

    /**
     * execute command
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass($this);

        $cmd = $this->getCommand($cmd);
        switch ($next_class) {
            default:
                $ret = &$this->$cmd();
                break;
        }
        return $ret;
    }

    /**
     * Retrieves the ilCtrl command
     *
     * @access public
     */
    public function getCommand($cmd)
    {
        return $cmd;
    }

    /**
     * @return ilTestPassOverviewTableGUI $tableGUI
     */
    public function buildPassOverviewTableGUI($targetGUI): ilTestPassOverviewTableGUI
    {
        $table = new ilTestPassOverviewTableGUI($targetGUI, '');

        $table->setPdfPresentationEnabled(
            $this->testrequest->isset('pdf') && $this->testrequest->raw('pdf') == 1
        );

        $table->setObjectiveOrientedPresentationEnabled(
            $this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()
        );

        return $table;
    }

    /**
     * Returns the list of answers of a users test pass
     *
     * @param array $result_array An array containing the results of the users test pass (generated by ilObjTest::getTestResult)
     * @param integer $active_id Active ID of the active user
     * @param integer $pass Test pass
     * @param boolean $show_solutions TRUE, if the solution output should be shown in the answers, FALSE otherwise
     * @return string HTML code of the list of answers
     * @access public
     */
    public function getPassListOfAnswers(
        &$result_array,
        $active_id,
        $pass,
        $show_solutions = false,
        $only_answered_questions = false,
        $show_question_only = false,
        $show_reached_points = false,
        $anchorNav = false,
        ilTestQuestionRelatedObjectivesList $objectivesList = null,
        ilTestResultHeaderLabelBuilder $testResultHeaderLabelBuilder = null
    ): string {
        $maintemplate = new ilTemplate("tpl.il_as_tst_list_of_answers.html", true, true, "Modules/Test");

        $counter = 1;
        // output of questions with solutions
        foreach ($result_array as $question_data) {
            if (!array_key_exists('workedthrough', $question_data)) {
                $question_data['workedthrough'] = 0;
            }
            if (!array_key_exists('qid', $question_data)) {
                $question_data['qid'] = -1;
            }

            if (($question_data["workedthrough"] == 1) || ($only_answered_questions == false)) {
                $template = new ilTemplate("tpl.il_as_qpl_question_printview.html", true, true, "Modules/TestQuestionPool");
                $question_id = $question_data["qid"] ?? null;
                if ($question_id !== null
                    && $question_id !== -1
                    && is_numeric($question_id)) {
                    $maintemplate->setCurrentBlock("printview_question");
                    $question_gui = $this->object->createQuestionGUI("", $question_id);
                    $question_gui->object->setShuffler($this->buildQuestionAnswerShuffler(
                        (int) $question_id,
                        (int) $active_id,
                        (int) $pass
                    ));
                    if (is_object($question_gui)) {
                        if ($anchorNav) {
                            $template->setCurrentBlock('block_id');
                            $template->setVariable('BLOCK_ID', "detailed_answer_block_act_{$active_id}_qst_{$question_id}");
                            $template->parseCurrentBlock();

                            $template->setCurrentBlock('back_anchor');
                            $template->setVariable('HREF_BACK_ANCHOR', "#pass_details_tbl_row_act_{$active_id}_qst_{$question_id}");
                            $template->setVariable('TXT_BACK_ANCHOR', $this->lng->txt('tst_back_to_question_list'));
                            $template->parseCurrentBlock();
                        }

                        if ($show_reached_points) {
                            $template->setCurrentBlock("result_points");
                            $template->setVariable("RESULT_POINTS", $this->lng->txt("tst_reached_points") . ": " . $question_gui->object->getReachedPoints($active_id, $pass) . " " . $this->lng->txt("of") . " " . $question_gui->object->getMaximumPoints());
                            $template->parseCurrentBlock();
                        }
                        $template->setVariable("COUNTER_QUESTION", $counter . ". ");
                        $template->setVariable("TXT_QUESTION_ID", $this->lng->txt('question_id_short'));
                        $template->setVariable("QUESTION_ID", $question_gui->object->getId());
                        $template->setVariable("QUESTION_TITLE", $this->object->getQuestionTitle($question_gui->object->getTitle()));

                        if ($objectivesList !== null) {
                            $objectives = $this->lng->txt('tst_res_lo_objectives_header') . ': ';
                            $objectives .= $objectivesList->getQuestionRelatedObjectiveTitles($question_gui->object->getId());
                            $template->setVariable("OBJECTIVES", $objectives);
                        }

                        $show_question_only = ($this->object->getShowSolutionAnswersOnly()) ? true : false;

                        $show_feedback = $this->isContextResultPresentation() && $this->object->getShowSolutionFeedback();
                        $show_best_solution = $this->isContextResultPresentation() && $show_solutions;
                        $show_graphical_output = $this->isContextResultPresentation();

                        if ($show_best_solution) {
                            $compare_template = new ilTemplate('tpl.il_as_tst_answers_compare.html', true, true, 'Modules/Test');
                            $compare_template->setVariable("HEADER_PARTICIPANT", $this->lng->txt('tst_header_participant'));
                            $compare_template->setVariable("HEADER_SOLUTION", $this->lng->txt('tst_header_solution'));
                            $result_output = $question_gui->getSolutionOutput($active_id, $pass, $show_graphical_output, false, $show_question_only, $show_feedback);
                            $best_output = $question_gui->getSolutionOutput($active_id, $pass, false, false, $show_question_only, false, true);

                            $compare_template->setVariable('PARTICIPANT', $result_output);
                            $compare_template->setVariable('SOLUTION', $best_output);
                            $template->setVariable('SOLUTION_OUTPUT', $compare_template->get());
                        } else {
                            $result_output = $question_gui->getSolutionOutput($active_id, $pass, $show_graphical_output, false, $show_question_only, $show_feedback);
                            $template->setVariable('SOLUTION_OUTPUT', $result_output);
                        }

                        $maintemplate->setCurrentBlock("printview_question");
                        $maintemplate->setVariable("QUESTION_PRINTVIEW", $template->get());
                        $maintemplate->parseCurrentBlock();
                        $counter++;
                    }
                }
            }
        }

        if ($testResultHeaderLabelBuilder !== null) {
            if ($pass !== null) {
                $headerText = $testResultHeaderLabelBuilder->getListOfAnswersHeaderLabel($pass + 1);
            } else {
                $headerText = $testResultHeaderLabelBuilder->getVirtualListOfAnswersHeaderLabel();
            }
        } else {
            $headerText = '';
        }

        $maintemplate->setVariable("RESULTS_OVERVIEW", $headerText);
        return $maintemplate->get();
    }

    protected function buildQuestionAnswerShuffler(
        int $question_id,
        int $active_id,
        int $pass_id
    ): Transformation {
        $fixedSeed = $this->buildFixedShufflerSeed($question_id, $pass_id, $active_id);

        return $this->randomGroup->shuffleArray(new GivenSeed($fixedSeed));
    }

    protected function buildFixedShufflerSeed(int $question_id, int $pass_id, int $active_id): int
    {
        $seed = ($question_id + $pass_id) * $active_id;

        if (is_float($seed) && is_float($seed = $active_id + $pass_id)) {
            $seed = $active_id;
        }

        $div = ceil((10 ** (ilTestPlayerAbstractGUI::FIXED_SHUFFLER_SEED_MIN_LENGTH - 1)) / $seed);

        if ($div > 1) {
            $seed = $seed * ($div + $seed % 10);
        }

        return (int) $seed;
    }

    /**
     * Returns the list of answers of a users test pass and offers a scoring option
     *
     * @param array $result_array An array containing the results of the users test pass (generated by ilObjTest::getTestResult)
     * @param integer $active_id Active ID of the active user
     * @param integer $pass Test pass
     * @param boolean $show_solutions TRUE, if the solution output should be shown in the answers, FALSE otherwise
     * @return string HTML code of the list of answers
     * @access public
     *
     * @deprecated
     */
    public function getPassListOfAnswersWithScoring(&$result_array, $active_id, $pass, $show_solutions = false): string
    {
        $maintemplate = new ilTemplate("tpl.il_as_tst_list_of_answers.html", true, true, "Modules/Test");
        $scoring = ilObjAssessmentFolder::_getManualScoring();

        $counter = 1;
        // output of questions with solutions
        foreach ($result_array as $question_data) {
            $question = $question_data["qid"];
            if (is_numeric($question)) {
                $question_gui = $this->object->createQuestionGUI("", $question);
                if (in_array($question_gui->object->getQuestionTypeID(), $scoring)) {
                    $template = new ilTemplate("tpl.il_as_qpl_question_printview.html", true, true, "Modules/TestQuestionPool");
                    $scoretemplate = new ilTemplate("tpl.il_as_tst_manual_scoring_points.html", true, true, "Modules/Test");
                    #mbecker: No such block. $this->tpl->setCurrentBlock("printview_question");
                    $template->setVariable("COUNTER_QUESTION", $counter . ". ");
                    $template->setVariable("QUESTION_TITLE", $this->object->getQuestionTitle($question_gui->object->getTitle()));
                    $points = $question_gui->object->getMaximumPoints();
                    if ($points == 1) {
                        $template->setVariable("QUESTION_POINTS", $points . " " . $this->lng->txt("point"));
                    } else {
                        $template->setVariable("QUESTION_POINTS", $points . " " . $this->lng->txt("points"));
                    }

                    $show_question_only = ($this->object->getShowSolutionAnswersOnly()) ? true : false;
                    $result_output = $question_gui->getSolutionOutput($active_id, $pass, $show_solutions, false, $show_question_only, $this->object->getShowSolutionFeedback(), false, true);

                    $solout = $question_gui->object->getSuggestedSolutionOutput();
                    if (strlen($solout)) {
                        $scoretemplate->setCurrentBlock("suggested_solution");
                        $scoretemplate->setVariable("TEXT_SUGGESTED_SOLUTION", $this->lng->txt("solution_hint"));
                        $scoretemplate->setVariable("VALUE_SUGGESTED_SOLUTION", $solout);
                        $scoretemplate->parseCurrentBlock();
                    }

                    $scoretemplate->setCurrentBlock("feedback");
                    $scoretemplate->setVariable("FEEDBACK_NAME_INPUT", $question);
                    $feedback = ilObjTest::getSingleManualFeedback((int) $active_id, (int) $question, (int) $pass)['feedback'] ?? '';
                    $scoretemplate->setVariable(
                        "VALUE_FEEDBACK",
                        ilLegacyFormElementsUtil::prepareFormOutput(
                            $this->object->prepareTextareaOutput($feedback, true)
                        )
                    );
                    $scoretemplate->setVariable("TEXT_MANUAL_FEEDBACK", $this->lng->txt("set_manual_feedback"));
                    $scoretemplate->parseCurrentBlock();

                    $scoretemplate->setVariable("NAME_INPUT", $question);
                    $this->ctrl->setParameter($this, "active_id", $active_id);
                    $this->ctrl->setParameter($this, "pass", $pass);
                    $scoretemplate->setVariable("FORMACTION", $this->ctrl->getFormAction($this, "manscoring"));
                    $scoretemplate->setVariable("LABEL_INPUT", $this->lng->txt("tst_change_points_for_question"));
                    $scoretemplate->setVariable("VALUE_INPUT", " value=\"" . assQuestion::_getReachedPoints($active_id, $question_data["qid"], $pass) . "\"");
                    $scoretemplate->setVariable("VALUE_SAVE", $this->lng->txt("save"));

                    $template->setVariable("SOLUTION_OUTPUT", $result_output);
                    $maintemplate->setCurrentBlock("printview_question");
                    $maintemplate->setVariable("QUESTION_PRINTVIEW", $template->get());
                    $maintemplate->setVariable("QUESTION_SCORING", $scoretemplate->get());
                    $maintemplate->parseCurrentBlock();
                }
                $counter++;
            }
        }
        if ($counter == 1) {
            // no scorable questions found
            $maintemplate->setVariable("NO_QUESTIONS_FOUND", $this->lng->txt("manscoring_questions_not_found"));
        }
        $maintemplate->setVariable("RESULTS_OVERVIEW", sprintf($this->lng->txt("manscoring_results_pass"), $pass + 1));

        ilYuiUtil::initDomEvent();

        return $maintemplate->get();
    }

    protected function getPassDetailsOverviewTableGUI(
        $result_array,
        $active_id,
        $pass,
        $targetGUI,
        $targetCMD,
        $questionDetailsCMD,
        $questionAnchorNav,
        ilTestQuestionRelatedObjectivesList $objectivesList = null,
        $multipleObjectivesInvolved = true
    ): ilTestPassDetailsOverviewTableGUI {
        $this->ctrl->setParameter($targetGUI, 'active_id', $active_id);
        $this->ctrl->setParameter($targetGUI, 'pass', $pass);

        $tableGUI = $this->buildPassDetailsOverviewTableGUI($targetGUI, $targetCMD);
        $tableGUI->setSingleAnswerScreenCmd($questionDetailsCMD);
        $tableGUI->setShowHintCount($this->object->isOfferingQuestionHintsEnabled());

        if ($objectivesList !== null) {
            $tableGUI->setQuestionRelatedObjectivesList($objectivesList);
            $tableGUI->setObjectiveOrientedPresentationEnabled(true);
        }

        $tableGUI->setMultipleObjectivesInvolved($multipleObjectivesInvolved);

        $tableGUI->setActiveId($active_id);
        $tableGUI->setShowSuggestedSolution(false);

        $usersQuestionSolutions = array();

        foreach ($result_array as $key => $val) {
            if ($key === 'test' || $key === 'pass') {
                continue;
            }

            if ($this->object->getShowSolutionSuggested() && strlen($val['solution'])) {
                $tableGUI->setShowSuggestedSolution(true);
            }

            if (isset($val['pass'])) {
                $tableGUI->setPassColumnEnabled(true);
            }

            $usersQuestionSolutions[$key] = $val;
        }

        $tableGUI->initColumns();
        $tableGUI->initFilter();

        $tableGUI->setFilterCommand($targetCMD . 'SetTableFilter');
        $tableGUI->setResetCommand($targetCMD . 'ResetTableFilter');

        $tableGUI->setData($usersQuestionSolutions);

        return $tableGUI;
    }

    /**
     * Returns HTML code for a signature field
     *
     * @return string HTML code of the date and signature field for the test results
     * @access public
     */
    public function getResultsSignature(): string
    {
        if ($this->object->getShowSolutionSignature() && !$this->object->getAnonymity()) {
            $template = new ilTemplate("tpl.il_as_tst_results_userdata_signature.html", true, true, "Modules/Test");
            $template->setVariable("TXT_DATE", $this->lng->txt("date"));
            $old_value = ilDatePresentation::useRelativeDates();
            ilDatePresentation::setUseRelativeDates(false);
            $template->setVariable("VALUE_DATE", ilDatePresentation::formatDate(new ilDate(time(), IL_CAL_UNIX)));
            ilDatePresentation::setUseRelativeDates($old_value);
            $template->setVariable("TXT_SIGNATURE", $this->lng->txt("tst_signature"));
            $template->setVariable("IMG_SPACER", ilUtil::getImagePath("spacer.png"));
            return $template->get();
        } else {
            return "";
        }
    }

    /**
     * Returns the user data for a test results output
     *
     * @param ilTestSession
     * @param integer $user_id The user ID of the user
     * @param boolean $overwrite_anonymity TRUE if the anonymity status should be overwritten, FALSE otherwise
     * @return string HTML code of the user data for the test results
     * @access public
     */
    public function getAdditionalUsrDataHtmlAndPopulateWindowTitle($testSession, $active_id, $overwrite_anonymity = false): string
    {
        if (!is_object($testSession)) {
            throw new InvalidArgumentException('Not an object, expected ilTestSession');
        }
        $template = new ilTemplate("tpl.il_as_tst_results_userdata.html", true, true, "Modules/Test");
        $user_id = $this->object->_getUserIdFromActiveId($active_id);
        if (strlen(ilObjUser::_lookupLogin($user_id)) > 0) {
            $user = new ilObjUser($user_id);
        } else {
            $user = new ilObjUser();
            $user->setLastname($this->lng->txt("deleted_user"));
        }
        $t = $testSession->getSubmittedTimestamp();
        if (!$t) {
            $t = $this->object->_getLastAccess($testSession->getActiveId());
        }

        if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
            $uname = $this->object->userLookupFullName($user_id, $overwrite_anonymity);
            $template->setCurrentBlock("name");
            $template->setVariable('TXT_USR_NAME', $this->lng->txt("name"));
            $template->setVariable('VALUE_USR_NAME', $uname);
            $template->parseCurrentBlock();
        }

        $title_matric = "";
        if (strlen($user->getMatriculation()) && (($this->object->getAnonymity() == false) || ($overwrite_anonymity))) {
            $template->setCurrentBlock("matriculation");
            $template->setVariable("TXT_USR_MATRIC", $this->lng->txt("matriculation"));
            $template->setVariable("VALUE_USR_MATRIC", $user->getMatriculation());
            $template->parseCurrentBlock();
            $title_matric = " - " . $this->lng->txt("matriculation") . ": " . $user->getMatriculation();
        }

        $invited_user = array_pop($this->object->getInvitedUsers($user_id));
        $title_client = '';
        if (isset($invited_user['clientip']) && $invited_user["clientip"] !== '') {
            $template->setCurrentBlock("client_ip");
            $template->setVariable("TXT_CLIENT_IP", $this->lng->txt("client_ip"));
            $template->setVariable("VALUE_CLIENT_IP", $invited_user["clientip"]);
            $template->parseCurrentBlock();
            $title_client = " - " . $this->lng->txt("clientip") . ": " . $invited_user["clientip"];
        }

        $template->setVariable("TXT_TEST_TITLE", $this->lng->txt("title"));
        $template->setVariable("VALUE_TEST_TITLE", $this->object->getTitle());

        // change the pagetitle (tab title or title in title bar of window)
        $pagetitle = $this->object->getTitle() . $title_matric . $title_client;
        $this->tpl->setHeaderPageTitle($pagetitle);

        return $template->get();
    }

    /**
     * Returns an output of the solution to an answer compared to the correct solution
     *
     * @param integer $question_id Database ID of the question
     * @param integer $active_id Active ID of the active user
     * @param integer $pass Test pass
     * @return string HTML code of the correct solution comparison
     * @access public
     */
    public function getCorrectSolutionOutput($question_id, $active_id, $pass, ilTestQuestionRelatedObjectivesList $objectivesList = null): string
    {
        $ilUser = $this->user;

        $test_id = $this->object->getTestId();
        $question_gui = $this->object->createQuestionGUI("", $question_id);

        $template = new ilTemplate("tpl.il_as_tst_correct_solution_output.html", true, true, "Modules/Test");
        $show_question_only = ($this->object->getShowSolutionAnswersOnly()) ? true : false;
        $result_output = $question_gui->getSolutionOutput($active_id, $pass, true, false, $show_question_only, $this->object->getShowSolutionFeedback(), false, false, true);
        $best_output = $question_gui->getSolutionOutput($active_id, $pass, false, false, $show_question_only, false, true, false, false);
        if ($this->object->getShowSolutionFeedback() && $this->testrequest->raw('cmd') != 'outCorrectSolution') {
            $specificAnswerFeedback = $question_gui->getSpecificFeedbackOutput(
                $question_gui->object->fetchIndexedValuesFromValuePairs(
                    $question_gui->object->getSolutionValues($active_id, $pass)
                )
            );
            if (strlen($specificAnswerFeedback)) {
                $template->setCurrentBlock("outline_specific_feedback");
                $template->setVariable("OUTLINE_SPECIFIC_FEEDBACK", $specificAnswerFeedback);
                $template->parseCurrentBlock();
            }
        }
        if ($this->object->isBestSolutionPrintedWithResult() && strlen($best_output)) {
            $template->setCurrentBlock("best_solution");
            $template->setVariable("TEXT_BEST_SOLUTION", $this->lng->txt("tst_best_solution_is"));
            $template->setVariable("BEST_OUTPUT", $best_output);
            $template->parseCurrentBlock();
        }
        $template->setVariable("TEXT_YOUR_SOLUTION", $this->lng->txt("tst_your_answer_was"));
        $template->setVariable("TEXT_SOLUTION_OUTPUT", $this->lng->txt("tst_your_answer_was")); // Mantis 28646. I don't really know why Ingmar renamed the placeholder, so
        // I set both old and new since the old one is set as well in several places.
        $maxpoints = $question_gui->object->getMaximumPoints();
        if ($maxpoints == 1) {
            $template->setVariable("QUESTION_TITLE", $this->object->getQuestionTitle($question_gui->object->getTitle()) . " (" . $maxpoints . " " . $this->lng->txt("point") . ")");
        } else {
            $template->setVariable("QUESTION_TITLE", $this->object->getQuestionTitle($question_gui->object->getTitle()) . " (" . $maxpoints . " " . $this->lng->txt("points") . ")");
        }
        if ($objectivesList !== null) {
            $objectives = $this->lng->txt('tst_res_lo_objectives_header') . ': ';
            $objectives .= $objectivesList->getQuestionRelatedObjectiveTitles($question_gui->object->getId());
            $template->setVariable('OBJECTIVES', $objectives);
        }
        $template->setVariable("SOLUTION_OUTPUT", $result_output);
        $template->setVariable("RECEIVED_POINTS", sprintf($this->lng->txt("you_received_a_of_b_points"), $question_gui->object->getReachedPoints($active_id, $pass), $maxpoints));
        $template->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
        $template->setVariable("BACKLINK_TEXT", "&lt;&lt; " . $this->lng->txt("back"));
        return $template->get();
    }

    /**
     * Output of the pass overview for a test called by a test participant
     *
     * @param ilTestSession $testSession
     * @param integer $active_id
     * @param integer $pass
     * @param boolean $show_pass_details
     * @param boolean $show_answers
     * @param boolean $show_question_only
     * @param boolean $show_reached_points
     * @access public
     */
    public function getResultsOfUserOutput($testSession, $active_id, $pass, $targetGUI, $show_pass_details = true, $show_answers = true, $show_question_only = false, $show_reached_points = false): string
    {
        $ilObjDataCache = $this->objCache;
        $template = new ilTemplate("tpl.il_as_tst_results_participant.html", true, true, "Modules/Test");

        if ($this->participantData instanceof ilTestParticipantData) {
            $user_id = $this->participantData->getUserIdByActiveId($active_id);
            $uname = $this->participantData->getConcatedFullnameByActiveId($active_id, false);
        } else {
            $user_id = $this->object->_getUserIdFromActiveId($active_id);
            $uname = $this->object->userLookupFullName($user_id, true);
        }

        if ($this->object->getAnonymity()) {
            $uname = $this->lng->txt('anonymous');
        }

        if ((($this->testrequest->isset('pass')) && (strlen($this->testrequest->raw("pass")) > 0)) || (!is_null($pass))) {
            if (is_null($pass)) {
                $pass = $this->testrequest->raw("pass");
            }
        }

        if (!is_null($pass)) {
            $testResultHeaderLabelBuilder = new ilTestResultHeaderLabelBuilder($this->lng, $ilObjDataCache);
            $objectivesList = null;

            if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
                $testSequence = $this->testSequenceFactory->getSequenceByActiveIdAndPass($active_id, $pass);
                $testSequence->loadFromDb();
                $testSequence->loadQuestions();

                $objectivesAdapter = ilLOTestQuestionAdapter::getInstance($testSession);

                $objectivesList = $this->buildQuestionRelatedObjectivesList($objectivesAdapter, $testSequence);
                $objectivesList->loadObjectivesTitles();

                $testResultHeaderLabelBuilder->setObjectiveOrientedContainerId($testSession->getObjectiveOrientedContainerId());
                $testResultHeaderLabelBuilder->setUserId($testSession->getUserId());
                $testResultHeaderLabelBuilder->setTestObjId($this->object->getId());
                $testResultHeaderLabelBuilder->setTestRefId($this->object->getRefId());
                $testResultHeaderLabelBuilder->initObjectiveOrientedMode();
            }

            $result_array = $this->object->getTestResult(
                $active_id,
                $pass,
                false,
                !$this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()
            );

            $user_id = $this->object->_getUserIdFromActiveId($active_id);
            $showAllAnswers = true;
            if ($this->object->isExecutable($testSession, $user_id)) {
                $showAllAnswers = false;
            }
            if ($show_answers) {
                $list_of_answers = $this->getPassListOfAnswers(
                    $result_array,
                    $active_id,
                    $pass,
                    ilSession::get('tst_results_show_best_solutions'),
                    $showAllAnswers,
                    $show_question_only,
                    $show_reached_points,
                    $show_pass_details,
                    $objectivesList,
                    $testResultHeaderLabelBuilder
                );
                $template->setVariable("LIST_OF_ANSWERS", $list_of_answers);
            }

            if ($show_pass_details) {
                $overviewTableGUI = $this->getPassDetailsOverviewTableGUI(
                    $result_array,
                    $active_id,
                    $pass,
                    $targetGUI,
                    "getResultsOfUserOutput",
                    '',
                    $show_answers,
                    $objectivesList
                );
                $overviewTableGUI->setTitle($testResultHeaderLabelBuilder->getPassDetailsHeaderLabel($pass + 1));
                $template->setVariable("PASS_DETAILS", $overviewTableGUI->getHTML());
            }

            $signature = $this->getResultsSignature();
            $template->setVariable("SIGNATURE", $signature);

            if ($this->object->isShowExamIdInTestResultsEnabled()) {
                $template->setCurrentBlock('exam_id_footer');
                $template->setVariable('EXAM_ID_VAL', ilObjTest::lookupExamId(
                    $testSession->getActiveId(),
                    $pass
                ));
                $template->setVariable('EXAM_ID_TXT', $this->lng->txt('exam_id'));
                $template->parseCurrentBlock();
            }
        }

        $template->setCurrentBlock('participant_back_anchor');
        $template->setVariable("HREF_PARTICIPANT_BACK_ANCHOR", "#tst_results_toolbar");
        $template->setVariable("TXT_PARTICIPANT_BACK_ANCHOR", $this->lng->txt('tst_back_to_top'));
        $template->parseCurrentBlock();

        $template->setCurrentBlock('participant_block_id');
        $template->setVariable("PARTICIPANT_BLOCK_ID", "participant_active_{$active_id}");
        $template->parseCurrentBlock();

        if ($this->isGradingMessageRequired()) {
            $gradingMessageBuilder = $this->getGradingMessageBuilder($active_id);
            $gradingMessageBuilder->buildList();

            $template->setCurrentBlock('grading_message');
            $template->setVariable('GRADING_MESSAGE', $gradingMessageBuilder->getList());
            $template->parseCurrentBlock();
        }


        $user_data = $this->getAdditionalUsrDataHtmlAndPopulateWindowTitle($testSession, $active_id, true);
        $template->setVariable("TEXT_HEADING", sprintf($this->lng->txt("tst_result_user_name"), $uname));
        $template->setVariable("USER_DATA", $user_data);

        $this->populateExamId($template, (int) $active_id, (int) $pass);
        $this->populatePassFinishDate($template, ilObjTest::lookupLastTestPassAccess($active_id, $pass));

        return $template->get();
    }

    /**
     * Returns the user and pass data for a test results output
     *
     * @param integer $active_id The active ID of the user
     * @return string HTML code of the user data for the test results
     * @access public
     */
    public function getResultsHeadUserAndPass($active_id, $pass): string
    {
        $template = new ilTemplate("tpl.il_as_tst_results_head_user_pass.html", true, true, "Modules/Test");
        $user_id = $this->object->_getUserIdFromActiveId($active_id);
        if (strlen(ilObjUser::_lookupLogin($user_id)) > 0) {
            $user = new ilObjUser($user_id);
        } else {
            $user = new ilObjUser();
            $user->setLastname($this->lng->txt("deleted_user"));
        }
        $title_matric = "";
        if (strlen($user->getMatriculation()) && (($this->object->getAnonymity() == false))) {
            $template->setCurrentBlock("user_matric");
            $template->setVariable("TXT_USR_MATRIC", $this->lng->txt("matriculation"));
            $template->parseCurrentBlock();
            $template->setCurrentBlock("user_matric_value");
            $template->setVariable("VALUE_USR_MATRIC", $user->getMatriculation());
            $template->parseCurrentBlock();
            $template->touchBlock("user_matric_separator");
            $title_matric = " - " . $this->lng->txt("matriculation") . ": " . $user->getMatriculation();
        }

        $invited_user = array_pop($this->object->getInvitedUsers($user_id));
        if (strlen($invited_user["clientip"] ?? '')) {
            $template->setCurrentBlock("user_clientip");
            $template->setVariable("TXT_CLIENT_IP", $this->lng->txt("client_ip"));
            $template->parseCurrentBlock();
            $template->setCurrentBlock("user_clientip_value");
            $template->setVariable("VALUE_CLIENT_IP", $invited_user["clientip"]);
            $template->parseCurrentBlock();
            $template->touchBlock("user_clientip_separator");
        }

        $template->setVariable("TXT_USR_NAME", $this->lng->txt("name"));
        $uname = $this->object->userLookupFullName($user_id, false);
        $template->setVariable("VALUE_USR_NAME", $uname);
        $template->setVariable("TXT_PASS", $this->lng->txt("scored_pass"));
        $template->setVariable("VALUE_PASS", $pass);
        return $template->get();
    }

    public function getQuestionResultForTestUsers(int $question_id, int $test_id): string
    {
        $question_gui = $this->object->createQuestionGUI("", $question_id);

        $this->object->setAccessFilteredParticipantList(
            $this->object->buildStatisticsAccessFilteredParticipantList()
        );

        $foundusers = $this->object->getParticipantsForTestAndQuestion($test_id, $question_id);
        $output = '';
        foreach ($foundusers as $active_id => $passes) {
            $resultpass = $this->object->_getResultPass($active_id);
            for ($i = 0; $i < count($passes); $i++) {
                if (($resultpass !== null) && ($resultpass == $passes[$i]["pass"])) {
                    if ($output) {
                        $output .= "<br /><br /><br />";
                    }

                    // check if re-instantiation is really neccessary
                    $question_gui = $this->object->createQuestionGUI("", $passes[$i]["qid"]);
                    $output .= $this->getResultsHeadUserAndPass($active_id, $resultpass + 1);
                    $question_gui->setRenderPurpose(assQuestionGUI::RENDER_PURPOSE_PRINT_PDF);
                    $output .= $question_gui->getSolutionOutput(
                        $active_id,
                        $resultpass,
                        $graphicalOutput = false,
                        $result_output = false,
                        $show_question_only = false,
                        $show_feedback = false
                    );
                }
            }
        }
        return $output;
    }

    /**
     * @return ilTestPassDetailsOverviewTableGUI
     */
    protected function buildPassDetailsOverviewTableGUI($targetGUI, $targetCMD): ilTestPassDetailsOverviewTableGUI
    {
        if (!isset($targetGUI->object) && method_exists($targetGUI, 'getTestObj')) {
            $targetGUI->object = $targetGUI->getTestObj();
        }

        $tableGUI = new ilTestPassDetailsOverviewTableGUI($this->ctrl, $targetGUI, $targetCMD);
        return $tableGUI;
    }

    protected function isGradingMessageRequired(): bool
    {
        if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
            return false;
        }

        if ($this->object->isShowGradingStatusEnabled()) {
            return true;
        }

        if ($this->object->isShowGradingMarkEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * @param integer $activeId
     * @return ilTestGradingMessageBuilder
     */
    protected function getGradingMessageBuilder($activeId): ilTestGradingMessageBuilder
    {
        $gradingMessageBuilder = new ilTestGradingMessageBuilder($this->lng, $this->object);

        $gradingMessageBuilder->setActiveId($activeId);

        return $gradingMessageBuilder;
    }

    protected function buildQuestionRelatedObjectivesList(ilLOTestQuestionAdapter $objectivesAdapter, ilTestQuestionSequence $testSequence): ilTestQuestionRelatedObjectivesList
    {
        $questionRelatedObjectivesList = new ilTestQuestionRelatedObjectivesList();

        $objectivesAdapter->buildQuestionRelatedObjectiveList($testSequence, $questionRelatedObjectivesList);

        return $questionRelatedObjectivesList;
    }

    protected function getFilteredTestResult($active_id, $pass, $considerHiddenQuestions, $considerOptionalQuestions): array
    {
        $ilDB = $this->db;
        $component_repository = $this->component_repository;

        $table_gui = $this->buildPassDetailsOverviewTableGUI($this, 'outUserPassDetails');
        $table_gui->initFilter();

        $questionList = new ilAssQuestionList($ilDB, $this->lng, $component_repository);

        $questionList->setParentObjIdsFilter(array($this->object->getId()));
        $questionList->setQuestionInstanceTypeFilter(ilAssQuestionList::QUESTION_INSTANCE_TYPE_DUPLICATES);

        foreach ($table_gui->getFilterItems() as $item) {
            if (substr($item->getPostVar(), 0, strlen('tax_')) == 'tax_') {
                $v = $item->getValue();

                if (is_array($v) && count($v) && !(int) $v[0]) {
                    continue;
                }

                $taxId = substr($item->getPostVar(), strlen('tax_'));
                $questionList->addTaxonomyFilter($taxId, $item->getValue(), $this->object->getId(), 'tst');
            } elseif ($item->getValue() !== false) {
                $questionList->addFieldFilter($item->getPostVar(), $item->getValue());
            }
        }

        $questionList->load();

        $filteredTestResult = array();

        $resultData = $this->object->getTestResult($active_id, $pass, false, $considerHiddenQuestions, $considerOptionalQuestions);

        foreach ($resultData as $resultItemKey => $resultItemValue) {
            if ($resultItemKey === 'test' || $resultItemKey === 'pass') {
                continue;
            }

            if (!$questionList->isInList($resultItemValue['qid'])) {
                continue;
            }

            $filteredTestResult[] = $resultItemValue;
        }

        return $filteredTestResult;
    }

    /**
     * @param string $content
     */
    protected function populateContent($content)
    {
        $this->tpl->setContent($content);
    }

    /**
     * @return ilTestResultsToolbarGUI
     */
    protected function buildUserTestResultsToolbarGUI(): ilTestResultsToolbarGUI
    {
        $toolbar = new ilTestResultsToolbarGUI($this->ctrl, $this->tpl, $this->lng);

        return $toolbar;
    }

    protected function outCorrectSolutionCmd()
    {
        $this->outCorrectSolution(); // cannot be named xxxCmd, because it's also called from context without Cmd in names
    }

    /**
     * Creates an output of the solution of an answer compared to the correct solution
     *
     * @access public
     */
    protected function outCorrectSolution()
    {
        if (!$this->object->getShowSolutionDetails()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt("no_permission"), true);
            $this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
        }

        $testSession = $this->testSessionFactory->getSession();
        $activeId = $testSession->getActiveId();

        if (!($activeId > 0)) {
            $this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
        }

        $this->ctrl->saveParameter($this, "pass");
        $pass = (int) $this->testrequest->raw("pass");

        $questionId = (int) $this->testrequest->raw('evaluation');

        $testSequence = $this->testSequenceFactory->getSequenceByActiveIdAndPass($activeId, $pass);
        $testSequence->loadFromDb();
        $testSequence->loadQuestions();

        if (!$testSequence->questionExists($questionId)) {
            ilObjTestGUI::accessViolationRedirect();
        }

        if ($this->getObjectiveOrientedContainer()->isObjectiveOrientedPresentationRequired()) {
            $testSequence = $this->testSequenceFactory->getSequenceByActiveIdAndPass($activeId, $pass);
            $testSequence->loadFromDb();
            $testSequence->loadQuestions();

            $objectivesAdapter = ilLOTestQuestionAdapter::getInstance($testSession);
            $objectivesList = $this->buildQuestionRelatedObjectivesList($objectivesAdapter, $testSequence);
            $objectivesList->loadObjectivesTitles();
        } else {
            $objectivesList = null;
        }

        $ilTabs = $this->tabs;

        if ($this instanceof ilTestEvalObjectiveOrientedGUI) {
            $ilTabs->setBackTarget(
                $this->lng->txt("tst_back_to_virtual_pass"),
                $this->ctrl->getLinkTarget($this, 'showVirtualPass')
            );
        } else {
            $ilTabs->setBackTarget(
                $this->lng->txt("tst_back_to_pass_details"),
                $this->ctrl->getLinkTarget($this, 'outUserPassDetails')
            );
        }
        $ilTabs->clearSubTabs();

        $this->tpl->setCurrentBlock("ContentStyle");
        $this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET", ilObjStyleSheet::getContentStylePath(0));
        $this->tpl->parseCurrentBlock();

        $this->tpl->setCurrentBlock("SyntaxStyle");
        $this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET", ilObjStyleSheet::getSyntaxStylePath());
        $this->tpl->parseCurrentBlock();

        $this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
        if ($this->object->getShowSolutionAnswersOnly()) {
            $this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print_hide_content.css", "Modules/Test"), "print");
        }

        $solution = $this->getCorrectSolutionOutput($questionId, $activeId, $pass, $objectivesList);

        $this->tpl->setContent($solution);
    }

    /**
     * @param ilTemplate $tpl
     * @param int $passFinishDate
     */
    public function populatePassFinishDate($tpl, $passFinishDate)
    {
        $oldValue = ilDatePresentation::useRelativeDates();
        ilDatePresentation::setUseRelativeDates(false);
        $passFinishDate = ilDatePresentation::formatDate(new ilDateTime($passFinishDate, IL_CAL_UNIX));
        ilDatePresentation::setUseRelativeDates($oldValue);
        $tpl->setVariable("PASS_FINISH_DATE_LABEL", $this->lng->txt('tst_pass_finished_on'));
        $tpl->setVariable("PASS_FINISH_DATE_VALUE", $passFinishDate);
    }

    /**
     * @param ilTemplate $tpl
     * @param int $activeId
     * @param int $pass
     */
    public function populateExamId(ilTemplate $tpl, int $activeId, int $pass)
    {
        if ($this->object->isShowExamIdInTestResultsEnabled()) {
            $tpl->setVariable("EXAM_ID_TXT", $this->lng->txt('exam_id'));
            $tpl->setVariable('EXAM_ID', ilObjTest::lookupExamId(
                $activeId,
                $pass
            ));
        }
    }

    public function getObject()
    {
        return $this->object;
    }
}
