<?php
require_once "./Services/Table/classes/class.ilTable2GUI.php";
require_once "./Services/Form/classes/class.ilTextInputGUI.php";
require_once "./Services/Form/classes/class.ilSelectInputGUI.php";
require_once "class.ilMStShowUserCourses.php";

//require_once("./Services/Container/classes/class.ilContainerObjectiveGUI.php");

/**
 * Class ilMStShowUserCoursesTableGUI
 *
 * @author  Martin Studer <ms@studer-raimann.ch>
 * @version 1.0.0
 */
class ilMStShowUserCoursesTableGUI extends ilTable2GUI {

    /**
     * @var ilCtrl $ctrl
     */
    protected $ctrl;
    /** @var  array $filter */
    protected $filter = array();
    protected $access;

    protected $ignored_cols;

    protected $custom_export_formats = array();
    protected $custom_export_generators = array();

    /** @var array */
    protected $numeric_fields = array("course_id");

    protected $usr_id;

    /**
     * @param ilMStListUsersGUI $parent_obj
     * @param string $parent_cmd
     */
    public function __construct($parent_obj, $parent_cmd = "index") {
        /** @var $ilCtrl ilCtrl */
        /** @var ilToolbarGUI $ilToolbar */
        /** @var $DIC ILIAS\DI\Container */
        global $ilCtrl, $ilToolbar, $DIC, $tpl, $lng, $ilUser;

        $this->ctrl = $ilCtrl;
        $this->access = ilMyStaffAcess::getInstance();

        $this->lng = $lng;
        $this->toolbar = $ilToolbar;

        $this->dic = $DIC;

        $this->usr_id = $_GET['usr_id'];

        $this->setPrefix('myst_su');
        $this->setFormName('myst_su');
        $this->setId('myst_su');

        parent::__construct($parent_obj, $parent_cmd, '');
        //$this->addMultiCommand('multiUserAccreditation', $this->pl->txt('accr_create_courses'));
        $this->setRowTemplate('tpl.list_courses_row.html',"Services/MyStaff");
        $this->setFormAction($this->ctrl->getFormAction($parent_obj));
        //$this->setDefaultOrderField('Datetime');
        $this->setDefaultOrderDirection('desc');

        $this->setShowRowsSelector(true);

        $this->setEnableTitle(true);
        $this->setDisableFilterHiding(true);
        $this->setEnableNumInfo(true);

        $this->setIgnoredCols(array());
        $this->setExportFormats(array(self::EXPORT_EXCEL, self::EXPORT_CSV));

        $this->setFilterCols(5);
        $this->initFilter();
        $this->addColumns();

        $this->parseData();
    }


    protected function parseData() {
        global $ilUser;

        $this->setExternalSorting(true);
        $this->setExternalSegmentation(true);
        $this->setDefaultOrderField('crs_title');

        $this->determineLimit();
        $this->determineOffsetAndOrder();


        //Permission Filter
        $arr_usr_id = $this->access->getOrguUsersOfCurrentUserWithShowStaffPermission();

        $this->filter['usr_id'] = $this->usr_id;
        $options = array(
            'filters' => $this->filter,
            'limit' => array(),
            'count' => true,
            'sort' => array('field' => $this->getOrderField(), 'direction' => $this->getOrderDirection())
        );
        $count = ilMStShowUserCourses::getData($arr_usr_id,$options);
        $options['limit'] = array('start' => (int)$this->getOffset(), 'end' => (int)$this->getLimit());
        $options['count'] = false;
        $data = ilMStShowUserCourses::getData($arr_usr_id,$options);

        $this->setMaxCount($count);
        $this->setData($data);
    }


    public function initFilter() {

        $item = new ilTextInputGUI($this->lng->txt("crs_title"), "crs_title");
        $this->addFilterItem($item);
        $item->readFromSession();
        $this->filter['crs_title'] = $item->getValue();

        // course members
        include_once("./Services/Form/classes/class.ilRepositorySelectorInputGUI.php");
        $item = new ilRepositorySelectorInputGUI($this->lng->txt("usr_filter_coursemember"), "course");
        $item->setParent($this->getParentObject());
        $item->setSelectText($this->lng->txt("mst_select_course"));
        $item->setHeaderMessage($this->lng->txt("mst_please_select_course"));
        $item->setClickableTypes(array("crs"));
        $this->addFilterItem($item);
        $item->readFromSession();
        $item->setParent($this->getParentObject());
        $this->filter["course"] = $item->getValue();

        //membership status
        $item = new ilSelectInputGUI($this->lng->txt('member_status'),'memb_status');
        $item->setOptions(array("" => $this->lng->txt("mst_opt_all"),
            ilMStListCourse::MEMBERSHIP_STATUS_WAITINGLIST => $this->lng->txt('mst_memb_status_waitinglist'),
            ilMStListCourse::MEMBERSHIP_STATUS_REGISTERED => $this->lng->txt('mst_memb_status_registered'),));
        $this->addFilterItem($item);
        $item->readFromSession();
        $this->filter["memb_status"] = $item->getValue();

        //learning progress status
        $item = new ilSelectInputGUI($this->lng->txt('learning_progress'),'lp_status');
        //+1 because LP_STATUS_NOT_ATTEMPTED_NUM is 0.
        $item->setOptions(array("" => $this->lng->txt("mst_opt_all"),
            ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM + 1 => $this->lng->txt(ilLPStatus::LP_STATUS_NOT_ATTEMPTED),
            ilLPStatus::LP_STATUS_IN_PROGRESS_NUM + 1 => $this->lng->txt(ilLPStatus::LP_STATUS_IN_PROGRESS),
            ilLPStatus::LP_STATUS_COMPLETED_NUM + 1 => $this->lng->txt(ilLPStatus::LP_STATUS_COMPLETED),
            ilLPStatus::LP_STATUS_FAILED_NUM + 1 => $this->lng->txt(ilLPStatus::LP_STATUS_FAILED)));
        $this->addFilterItem($item);
        $item->readFromSession();
        $this->filter["lp_status"] = $item->getValue();
        if($this->filter["lp_status"])
        {
            $this->filter["lp_status"] = $this->filter["lp_status"] - 1;
        }
    }


    /**
     * @return array
     */
    public function getSelectableColumns() {
        $cols = array();

        $cols['crs_title'] = array('txt' => $this->lng->txt('crs_title'), 'default' => true, 'width' => 'auto','sort_field' => 'crs_title');
        $cols['usr_reg_status'] = array('txt' => $this->lng->txt('member_status'), 'default' => true, 'width' => 'auto','sort_field' => 'reg_status');
        $cols['usr_lp_status'] = array('txt' => $this->lng->txt('learning_progress'), 'default' => true, 'width' => 'auto','sort_field' => 'lp_status');

        return $cols;
    }


    private function addColumns() {

        foreach ($this->getSelectableColumns() as $k => $v) {
            if ($this->isColumnSelected($k)) {
                if (isset($v['sort_field'])) {
                    $sort = $v['sort_field'];
                } else {
                    $sort = NULL;
                }
                $this->addColumn($v['txt'], $sort, $v['width']);
            }
        }

    }


    /**
     * @param ilMStListCourse $my_staff_course
     */
    public function fillRow($my_staff_course)
    {

        $propGetter = Closure::bind(function ($prop) {
            return $this->$prop;
        }, $my_staff_course, $my_staff_course);


        /*$this->tpl->setCurrentBlock('record_id');
        $this->tpl->setVariable('RECORD_ID',  '');
        $this->tpl->parseCurrentBlock();*/

        foreach ($this->getSelectableColumns() as $k => $v) {
            if ($this->isColumnSelected($k)) {
                switch ($k) {
                    case 'usr_reg_status':
                        $this->tpl->setCurrentBlock('td');
                        $this->tpl->setVariable('VALUE', ilMStListCourse::getMembershipStatusText($my_staff_course->getUsrRegStatus()));
                        $this->tpl->parseCurrentBlock();
                        break;
                    case 'usr_lp_status':
                        $f = $this->dic->ui()->factory();
                        $renderer = $this->dic->ui()->renderer();
                        $lp_icon = $f->image()->standard(ilLearningProgressBaseGUI::_getImagePathForStatus($my_staff_course->getUsrLpStatus()), ilLearningProgressBaseGUI::_getStatusText((int)$my_staff_course->getUsrLpStatus()));
                        $this->tpl->setCurrentBlock('td');
                        $this->tpl->setVariable('VALUE', $renderer->render($lp_icon) . ' ' . ilLearningProgressBaseGUI::_getStatusText((int)$my_staff_course->getUsrLpStatus()));
                        $this->tpl->parseCurrentBlock();
                        break;
                    default:
                        if ($propGetter($k) !== NULL) {
                            $this->tpl->setCurrentBlock('td');
                            $this->tpl->setVariable('VALUE', (is_array($propGetter($k)) ? implode(", ", $propGetter($k)) : $propGetter($k)));
                            $this->tpl->parseCurrentBlock();
                        } else {
                            $this->tpl->setCurrentBlock('td');
                            $this->tpl->setVariable('VALUE', '&nbsp;');
                            $this->tpl->parseCurrentBlock();
                        }
                        break;
                }
            }
        }
    }



    /**
     * @param ilExcel $a_excel	excel wrapper
     * @param int    $a_row
     * @param ilMyStaffUser $my_staff_user
     */
    protected function fillRowExcel(ilExcel $a_excel, &$a_row, $my_staff_user) {
        $col = 0;

        $propGetter = Closure::bind(  function($prop){return $this->$prop;}, $my_staff_user, $my_staff_user);

        foreach ($this->getSelectableColumns() as $k => $v) {
            if ($this->isColumnSelected($k)) {
                $a_excel->setCell($a_row, $col, strip_tags($propGetter($k)));
                $col ++;
            }
        }
    }

    /**
     * @param object $a_csv
     * @param ilMyStaffUser $my_staff_user
     */
    protected function fillRowCSV($a_csv, $my_staff_user) {

        $propGetter = Closure::bind(  function($prop){return $this->$prop;}, $my_staff_user, $my_staff_user);

        foreach ($this->getSelectableColumns() as $k => $v) {
            if (!in_array($k, $this->getIgnoredCols()) && $this->isColumnSelected($k)) {
                $a_csv->addColumn(strip_tags($propGetter($k)));
            }
        }
        $a_csv->addRow();
    }

    /**
     * @return bool
     */
    public function numericOrdering($sort_field) {
        return in_array($sort_field, array());
    }


    /**
     * @param array $ignored_cols
     */
    public function setIgnoredCols($ignored_cols) {
        $this->ignored_cols = $ignored_cols;
    }


    /**
     * @return array
     */
    public function getIgnoredCols() {
        return $this->ignored_cols;
    }
}
?>