<?php
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverAlert;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\JavaScriptExecutor;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverContextClickAction;

class BOT_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        //$this->load->database();
        //$this->load->library('encryption');
    }
    public function dbquery($userId, $trnrefno)
    {
       $sql = "SELECT a.*,b.is_pan_checked FROM hfccustdata a LEFT JOIN bot_aps_tracking b on a.TRNREFNO = b.TRNREFNO WHERE b.status in ('N','E','P') AND a.TRNREFNO = '$trnrefno' ORDER BY id";
        $rows = $this->db->query($sql)->result_array();
        return $rows;
    }

    public function logout($driver,$trnrefno){
        $sql = "select is_logged_in from bot_aps_tracking where TRNREFNO = $trnrefno";
        $rows = $this->db->query($sql)->result_array();
        $loggedin = $rows[0]['is_logged_in'];
        if($loggedin == 0){
            $driver->quit();
            try {
                $this->closeIESessions();
            } catch (Exception $e) {
                
            }
        }else{
            sleep(2);
            $allWindows = $driver->getWindowHandles();
            // $cuntswind = count($allWindows);
            // if($cuntswind > 1){
            //     foreach ($allWindows as $key=> $value) {
            //         if($key == 0){continue;}
            //         if($key!=0){
            //             sleep(2);
            //             $driver->switchTo()->window($value)->close(); 
            //         }
            //     } 
            // }

            $driver->switchTo()->window($allWindows[0]); 
            $driver->switchTo()->defaultContent();
            
            try{
                $driver->wait(5,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            );

             $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            }
            catch (Exception $e) {
                $driver->quit();
            try {
                $this->closeIESessions();
                $sql = "UPDATE bot_aps_tracking SET is_logged_in = '0' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
            } catch (Exception $e) {
            }
            
            }

            $driver->switchTo()->frame($frame);

            $selector = WebDriverBy::xpath('/html/body/form/table/tbody/tr[1]/td[2]/table/tbody/tr[1]/td[8]/a');


            try{
                $driver->wait(60, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
            }
            catch (Exception $e) {
                $driver->quit();
            try {
                $this->closeIESessions();
                $sql = "UPDATE bot_aps_tracking SET is_logged_in = '0' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
            } catch (Exception $e) {
            }
            
            }

            $driver->findElement($selector)->click(); 
            try{
                $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
                $driver->switchTo()->alert()->accept();
            }
            catch (Exception $e) {

            }

            $sql = "UPDATE bot_aps_tracking SET is_logged_in = '0' WHERE TRNREFNO = '".$trnrefno."'";
            $qparent = $this->db->query($sql);

            $driver->quit();
            try {
                $this->closeIESessions();
            } catch (Exception $e) {
                
            }
             
        }

            $driver->quit();
            try {
                $this->closeIESessions();
                $sql = "UPDATE bot_aps_tracking SET is_logged_in = '0' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
            } catch (Exception $e) {
            }
            //exit();
    } 



    public function start($userId, $trnrefno){
       
       $fincoreload = 'false';
        try {
            $this->closeIESessions();
        }catch (Exception $e) {
            
        }

        $rows = $this->dbquery($userId, $trnrefno);

        if(count($rows) > 0){

            $row = $rows[0];
            $capabilities = DesiredCapabilities::internetExplorer();            
            $driver = RemoteWebDriver::create('http://localhost:5555/wd/hub', $capabilities, 5000);
            $localIP = getHostByName(getHostName());
            $driver->manage()->window()->maximize();
            /*$sql = "UPDATE bot_aps_tracking SET status = 'P', ip_address = '".$localIP."', start_time = '".date("Y-m-d H:i:s")."' WHERE TRNREFNO = '".$trnrefno."'";
            $qparent = $this->db->query($sql);*/
               try {
                    $this->loginCust($driver,$trnrefno,$userId);

                } catch (Exception $e) {
                    $sql = "UPDATE bot_aps_tracking SET start_time = '' WHERE TRNREFNO = '".$trnrefno."'";
                    $qparent = $this->db->query($sql);
                    $this->addException($e, $trnrefno, $userId, "Finacle Login Error", $driver);   
                }

            if($row['is_pan_checked'] != 1){

                try {
                    $this->startfincoreProcess($driver,$userId,$row);
                } catch (Exception $e) {
                    $this->addException($e, $trnrefno, $userId, "Search Customer ID Error", $driver);
                    
                }

                $sql = "UPDATE bot_aps_tracking SET is_pan_checked = 1, end_time = '".date("Y-m-d H:i:s")."' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
                $fincoreload = 'true';

            }
            
            $rows = $this->dbquery($userId, $trnrefno);
            if(count($rows) > 0){
                $row = $rows[0];
            }

            try {
                $this->startCRMProcess($driver, $userId, $row);
            } catch (Exception $e) {
                $sql = "UPDATE bot_aps_tracking SET start_time = '' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
                $this->addException($e, $trnrefno, $userId, "Customer ID Creation Error", $driver);
            }

            $rows = $this->dbquery($userId, $trnrefno);

            if(count($rows) > 0){
                $row = $rows[0];
            }
            try {
                $this->accountcreate($driver, $userId, $row,$trnrefno,$fincoreload);
            } catch (Exception $e) {
                $sql = "UPDATE bot_aps_tracking SET start_time = '' WHERE TRNREFNO = '".$trnrefno."'";
                $qparent = $this->db->query($sql);
                $this->addException($e, $trnrefno, $userId, "Account Creation Error", $driver);
            }

            //$this->creditdebit($driver);
            try {
            $this->logout($driver,$trnrefno);
            } catch (Exception $e) {
                $this->addException($e, $trnrefno, $userId, "Finacle Log Out Error", $driver);
            }
            $driver->quit();
            try {
                $this->closeIESessions();
            } catch (Exception $e) {
                
            }
                
        }
         
    }

    public function loginCust($driver,$trnrefno,$userid){
        //$url = "https://ijprsunt7-04-ld18.icicibankltd.com:8212/SSO/ui/SSOLogin.jsp";
        $url = "https://hfc10xlive.icicibankltd.com:8212/SSO/ui/SSOLogin.jsp";
        $driver->get($url);
        //exit;
        $tryCount = 0;
        $this->moreInfoContainer($driver,$tryCount);
        sleep(3);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("usertxt"))
        );
        sleep(6);
        $driver->findElement(WebDriverBy::id("usertxt"))->sendKeys($this->getUserName());
        $driver->findElement(WebDriverBy::id("passtxt"))->sendKeys($this->getPassword());

        $checkclick = $driver->findElement(WebDriverBy::id("Submit"))->click();

        if($checkclick != null){
           try{
            
                $driver->switchTo()->defaultContent();
                $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
                );
                $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
                $driver->switchTo()->frame($frame);

                $driver->findElement(WebDriverBy::id("Submit2"))->click();
                $driver->wait(60,1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
                );
                $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
                $driver->switchTo()->frame($frame);
                $driver->wait(60,1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("usertxt"))
                );
                sleep(6);
                $driver->findElement(WebDriverBy::id("usertxt"))->sendKeys($this->getUserName());
                $driver->findElement(WebDriverBy::id("passtxt"))->sendKeys($this->getPassword());

                $checkclick = $driver->findElement(WebDriverBy::id("Submit"))->click();

        }catch (Exception $e) {  

        } 
        }

        // $checktext = $driver->findElements(WebDriverBy::xpath("/html/frameset/frame/html/body/table/tbody/tr/td/table/tbody/tr/td/table/tbody/tr/td/table/tbody/tr/td/font"))->getText();

        // if(count($checktext) > 0)
        // {
        //     $new = $checktext[0]->getText();
        // }

        // echo $new;exit;

        //$textcheck = $driver->findElement(WebDriverBy::linkText("Invalid User ID or Password."));


        //exit;

        /*if($checkclick != null){

           $driver->findElement(WebDriverBy::id("Submit2"))->click();
           $driver->findElement(WebDriverBy::id("usertxt"))->sendKeys($this->getUserName());
           $driver->findElement(WebDriverBy::id("passtxt"))->sendKeys($this->getPassword());
        }*/
      
        $sql = "UPDATE bot_aps_tracking SET userid = $userid,start_time = '".date("Y-m-d H:i:s")."', is_logged_in = '1' WHERE TRNREFNO = '".$trnrefno."'";
        $qparent = $this->db->query($sql);
    }

    public function startfincoreProcess($driver,$userId,$row){
        sleep(2);

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("appSelect"))
        );

        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("appSelect")));
        $element->selectByValue('CoreServer');

        $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CoreServer"))
        );

        $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
        $driver->switchTo()->frame($frame);

        sleep(5);
        $frame = $driver->findElement(WebDriverBy::id("FINW"));
        $driver->switchTo()->frame($frame);
        sleep(3);
        try {
                $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
                sleep(1);
                $driver->switchTo()->alert()->accept();
            } catch (Exception $e) {        
        }

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("menuName"))
        );

        $element = $driver->findElement(WebDriverBy::id("menuName"));
        $element->sendKeys("HOAACTD");
        $driver->executeScript('document.getElementById("menuName").value = "HOAACTD";');
        $element->sendKeys(array(WebDriverKeys::TAB));
        $driver->findElement(WebDriverBy::id("gotomenu"))->click();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("sLnk2"))
        );
        $driver->findElement(WebDriverBy::id("sLnk2"))->click();
        sleep(2);
        $driver->findElement(WebDriverBy::id("sLnk2"))->click();
       
        $handles = $driver->getWindowHandles();
        $driver->switchTo()->window(end($handles));

        $this->checkPanStatus($driver, $row['PANGIR1'], $row['TRNREFNO'], 1);

        if(!empty($row['JH1PAN'])){
            $this->checkPanStatus($driver, $row['JH1PAN'], $row['TRNREFNO'],2);
        }

        if(!empty($row['JH2PAN'])){
            $this->checkPanStatus($driver,$row['JH2PAN'],$row['TRNREFNO'],3);
        }

        $allWindows = $driver->getWindowHandles();
        $driver->switchTo()->window(end($allWindows))->close();
        $driver->switchTo()->window($allWindows[0]);   
    }

    public function checkPanStatus($driver, $pan, $trnrefno, $cifid){

        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("html/frameset/frame"))
        );
        $frame = $driver->findElement(WebDriverBy::xpath("html/frameset/frame"));
        $driver->switchTo()->frame($frame);
        sleep(3);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("docNo"))
        );
        $driver->findElement(WebDriverBy::id("docNo"))->clear();
        $driver->findElement(WebDriverBy::id("docNo"))->sendKeys($pan);
        $driver->findElement(WebDriverBy::id("Ok"))->click();
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("GetCustIdResults"))
        );
        $frame = $driver->findElement(WebDriverBy::name("GetCustIdResults"));
        $driver->switchTo()->frame($frame);
        sleep(2);
        $results = $driver->findElements(WebDriverBy::xpath("/html/body/form/span/table/tbody/tr/td/table/tbody/tr/td/table[2]/tbody/tr[2]/td[1]/font/a"));

        if(count($results) > 0){
            $custId = $results[0]->getText();
            $sql = "UPDATE hfccustdata SET cifid_$cifid = '".$custId."',is_existing_cust_$cifid ='1' WHERE TRNREFNO = '".$trnrefno."'";
            $qparent = $this->db->query($sql);
        }
    }

public function checkCustIdExist($pancol,$pan,$cifid){

        $sql = "select id from hfccustdata where $pancol = '".$pan."' and (cifid_1 !='' or cifid_2 !='' or cifid_3 !='') and $cifid !='' ";

        $qparent = $this->db->query($sql);
        // $row = $qparent->result_array();
        // print_r($row);exit;
        // //$last_id = $row[0]['id'];
        return $qparent->num_rows();

        // $sql = "select id from hfccustdata where $pancol = '".$pan."' and (cifid_1 !='' or cifid_2 !='' or cifid_3 !='') and id !='".$last_id."'";

        // $qparent = $this->db->query($sql);

        // $numrows1 = $qparent->num_rows();

        // if($numrows == 0 &&  $numrows1 == 0){
        //     return 0;
        // }else{
        //     return 1;
        // }

}

public function startCRMProcess($driver,$userId,$row){
        $i=0;
        $j=0;
        
        $checkCust1 = $this->checkCustIdExist('PANGIR1',$row['PANGIR1'],'cifid_1');
        //echo $checkCust1;exit;
         if($checkCust1 == 0){    
            if((empty($row['cifid_1']) || is_null($row['cifid_1'])) && !empty($row['PANGIR1'])){
                $i++;
                try {
                    $this->addNewCustId($driver, $row,'cifid_1',$row['DOB'],$row['NAME'],$row['PANGIR1'],$i,$userId);
                } catch (Exception $e) {
                    $this->addException($e, $row['TRNREFNO'], $userId, "Start NEW CIFID1", $driver);
                    }
                
                }
            }

    $checkCust2 = $this->checkCustIdExist('JH1PAN',$row['JH1PAN'],'cifid_2');
    
    if($checkCust2 == 0){
        if((is_null($row['cifid_2']) || empty($row['cifid_2'])) && !empty($row['JH1PAN'])){
            $i++;
            try {
                $this->addNewCustId($driver, $row,'cifid_2','01/01/1900',$row['JH1NAME'],$row['JH1PAN'],$i,$userId);
            } catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userId, "Start NEW CIFID2", $driver);
            }
         }
    }

    $checkCust3 = $this->checkCustIdExist('JH2PAN',$row['JH2PAN'],'cifid_3');

    if($checkCust3 == 0){
        if((is_null($row['cifid_3']) || empty($row['cifid_3'])) && !empty($row['JH2PAN'])){
            $i++;
            try {
                $this->addNewCustId($driver, $row,'cifid_3','01/01/1900',$row['JH2NAME'],$row['JH2PAN'],$i,$userId);
            } catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userId, "Start NEW CIFID3", $driver);
            }
          }  
    }

    if(!empty($row['cifid_1']) && $row['is_existing_cust_1'] == 1 && $row['edit_cifid_1'] != 1){
        $j++;
        try {
            $this->editcust($driver,$row,'cifid_1',$row['DOB'],$row['NAME'],$row['PANGIR1'],$userId,$j);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userId, "EDIT CIFID1", $driver);
       }
    }
    if(!empty($row['cifid_2']) && $row['is_existing_cust_2'] == 1 && $row['edit_cifid_2'] != 1){
        $j++;
       try {
           $this->editcust($driver, $row,'cifid_2','01/01/1900',$row['JH1NAME'],$row['JH1PAN'],$userId,$j);
        }
        catch (Exception $e) {
             $this->addException($e, $row['TRNREFNO'], $userId, "EDIT CIFID2", $driver);
        }
    }
    if(!empty($row['cifid_3']) && $row['is_existing_cust_3'] == 1 && $row['edit_cifid_3'] != 1){
        $j++;
          try {
             $this->editcust($driver, $row,'cifid_3','01/01/1900',$row['JH2NAME'],$row['JH2PAN'],$userId,$j);
        }
        catch (Exception $e) {
             $this->addException($e, $row['TRNREFNO'], $userId, "EDIT CIFID3", $driver);
        }
    }
}
  
    public function addNewCustId($driver, $row, $cfid, $dob, $name, $pan,$num,$userid){
       if($num == '1'){
            $driver->switchTo()->defaultContent();

            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            );
            $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            $driver->switchTo()->frame($frame);

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("appSelect"))
            );
            
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("appSelect")));
            $element->selectByValue('CRMServer');

            try {
                $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
                $driver->switchTo()->alert()->accept();
            } catch (Exception $e) {}

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
            );

            $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
            $driver->switchTo()->frame($frame);
            sleep(2);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("ScreensTOCFrm"))
            );
            $frame = $driver->findElement(WebDriverBy::name("ScreensTOCFrm"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Functionmain"))
            );
            $frame = $driver->findElement(WebDriverBy::name("Functionmain"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("screen1"))
            );
            $driver->findElement(WebDriverBy::id("screen1"))->click();
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("1504"))
            );
            $frame = $driver->findElement(WebDriverBy::name("1504"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("view2"))
            );
            $driver->findElement(WebDriverBy::id("view2"))->click();
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("subview20"))
            );
            $driver->findElement(WebDriverBy::id("subview20"))->click(); 
        }
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
        try {
            $this->generaltabentries($driver, $row, $cfid, $dob, $name, $pan,$userid);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "General Tab Entry Error", $driver);
        }

        //Demographic Process

        $handle = $driver->getWindowHandles();
        $driver->switchTo()->window($handle[0]);
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabViewFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabViewFrm"));
        $driver->switchTo()->frame($frame);

        //DEMOGRAPHIC TAB
        $driver->findElement(WebDriverBy::id("tab1"))->click();
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab1"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab1"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DemographicModBO.Nationality"))
        );
        $element = $driver->findElement(WebDriverBy::name("DemographicModBO.Nationality"));
        $element->sendKeys('INDIAN');
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Cat_DemographicModBO.Nationality"))
        );
        $element = $driver->findElement(WebDriverBy::name("Cat_DemographicModBO.Nationality"));
        $element->sendKeys('INDIAN');
        sleep(1);
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("DemographicModBO.Marital_Status")));
        $element->selectByValue('OTHER');
        sleep(1);
        $driver->findElement(WebDriverBy::id("tab_tpageEDet"))->click();
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("DemographicModBO.Employment_Status")));
        $element->selectByValue('Other');
        $driver->findElement(WebDriverBy::id("tab_tpageIExp"))->click();
        $element = $driver->findElement(WebDriverBy::name("3_DemographicBO.Annual_Salary_Income"));
        $element->sendKeys('500000');
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame); 
        $driver->wait(40,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::name("buttonFrm"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::id("submitBut"))->click();
        $driver->wait(60,1000)->until(WebDriverExpectedCondition::alertIsPresent());
        $alert = $driver->switchTo()->alert();
        $custdata = $alert->getText();
        $custexp = explode(': ',$custdata);
        $custId = end($custexp);
        $this->saveCustID($custId,$row['TRNREFNO'],$cfid);
        $alert->accept();
        $handle = $driver->getWindowHandles();
        $driver->switchTo()->window(end($handle));
        sleep(1);
        $driver->wait(40,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        sleep(1);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("buttonFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("buttonFrm"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::id("saveBut"))->click();
        $driver->wait(60,1000)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();
        // final submit done flag maintaine
        $sql = "UPDATE bot_aps_tracking SET is_processed = 'Yes' where TRNREFNO = '".$row['TRNREFNO']."'";
        $qparent = $this->db->query($sql);

        sleep(5);
        $handle = $driver->getWindowHandles();
        $driver->switchTo()->window($handle[0]);
        $driver->switchTo()->defaultContent();

    }
    public function switchframeCust($driver){
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
    }
    public function generaltabentries($driver, $row, $cfid, $dob, $name, $pan,$userid){

        if(!empty($name)){
            
            $exp = explode(' ',trim($name));

            $tnamecount = count($exp);

            $firstname='';
            $middlename='';
            $lastname='';
            if($tnamecount == 3){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = '';
                $lastname = trim($exp[2]);


            }
            elseif($tnamecount == 4){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = trim($exp[2]);
                $lastname = trim($exp[3]);

            }elseif($tnamecount == 5){

                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]).' '.trim($exp[2]);
                $middlename = trim($exp[3]);
                $lastname = trim($exp[4]);

            }
            if($salutaion =="MR" || $salutaion =="MR."){
                $salutaion = "MR";
                $gender = 'M';
            }else{
                $salutaion = "MRS";
                $gender = 'F';
            }
            if($middlename !=''){
                $newname = $salutaion.' '.$firstname.' '.$middlename.' '.$lastname;
            }else{
                $newname = $salutaion.' '.$firstname.' '.$lastname;
            }

        

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountModBO.Gender"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.Gender")));
            $element->selectByValue($gender);
            $element = $driver->findElement(WebDriverBy::name("AccountModBO.Salutation_code"));
            $element->sendKeys($salutaion);
            sleep(1);
            $element->sendKeys(array(WebDriverKeys::TAB));
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_First_Name"));
            $element->sendKeys($firstname);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_Middle_Name"));
            $element->sendKeys($middlename);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_Last_Name"));
            $element->sendKeys($lastname);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.short_name"));
            $element->sendKeys($firstname);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Name"));
            $element->sendKeys($name);
            if(!empty($dob)){
                $element = $driver->findElement(WebDriverBy::name("3_AccountBO.Cust_DOB"));
                $element->sendKeys($dob);
            }
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.CustomerNREFlg")));
            $element->selectByValue('N');
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Cat_AccountBO.Constitution_Code"))
            );
            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Constitution_Code"));
            $element->sendKeys('RTL-INDIVIDUAL');
            $element = $driver->findElement(WebDriverBy::name("AccountModBO.Tds_tbl"));
            $element->sendKeys('TDSI');
            sleep(1);
            $element->sendKeys(array(WebDriverKeys::TAB));

            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.Customer_Level_Provisioning")));
            $element->selectByValue('N');

            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.PurgeFlag")));
            $element->selectByValue('N');
            $driver->findElement(WebDriverBy::id("rownative2"))->click();
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.Introd_Status")));
            $element->selectByValue('PAN');
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountModBO.IntroducerSalutation")));
            $element->selectByValue($salutaion);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.IntroducerName"));
            $element->sendKeys($newname);

        }
        $driver->switchTo()->defaultContent();    
        //Address Details
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Address Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::id("tab_tpageCont3"))->click();
        
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.preferredAddress"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.Address.preferredAddress")));
        $element->selectByValue('Mailing');
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Add Address Details"))
        );
        $driver->findElement(WebDriverBy::name("Add Address Details"))->click();
        sleep(6);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        try{
            sleep(5);
            $tryCount = 0;
            $this->moreInfoContainer($driver,$tryCount);
        }catch (Exception $e) {

        }

        if(!empty($row['ADD1'])){

            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.PreferredFormat"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.Address.PreferredFormat")));
            $element->selectByValue('FREE_TEXT_FORMAT');
            try {
                $driver->wait(10)->until(WebDriverExpectedCondition::alertIsPresent());
                $driver->switchTo()->alert()->accept();
            } catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userid, "Address Free text start alert", $driver);
            }

            /*try {
                $driver->wait(10)->until(WebDriverExpectedCondition::alertIsPresent());
                $driver->switchTo()->alert()->accept();
            } catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userid, "address1 alert", $driver);
            }*/

            sleep(2);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.addressCategory"))
            );
            sleep(3);
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.Address.addressCategory")));
            $element->selectByValue('Mailing');
            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.address_Line1"))
             );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.address_Line1"));
            $element->sendKeys($row['ADD1']);
        }
        if(!empty($row['ADD2'])){
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.address_Line2"))
            );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.address_Line2"));
            $element->sendKeys($row['ADD2']);
            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.city"));

        
            $sql = "select city_name from city_data where city_name = '".$row['CITY']."'";
            $qparent = $this->db->query($sql);
            $countcity = $qparent->num_rows();
            try{
                if($countcity >0){
                    $element->sendKeys($row['CITY']);
                }
            }catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userid, "City does not Exist", $driver);
            }
            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.state"));
            //if(trim($row['STATE']))

            $enterstate = trim($row['STATE']);

            //echo $enterstate;

            if(strtolower($enterstate) == strtolower('ANDAMAN AND NICOBAR') || strtolower($enterstate) == strtolower('ANDAMAN & NICOBAR') || strtolower($enterstate) == strtolower('ANDAMANNICOBAR') || strtolower($enterstate) == strtolower('ANDAMAN')){

                $state = 'ANDAMAN & NICOBAR';

            }
            else if(strtolower($enterstate) == strtolower('JAMMU AND KASHMIR') || strtolower($enterstate) == strtolower('JAMMU & KASHMIR') || strtolower($enterstate) == strtolower('JAMMUKASHMIR') || strtolower($enterstate) == strtolower('JAMMU')){

                $state = 'JAMMU & KASHMIR';

            }
            else if(strtolower($enterstate) == strtolower('DAMAN AND DIU') || strtolower($enterstate) == strtolower('DAMAN & DIU') || strtolower($enterstate) == strtolower('DAMANDIU') || strtolower($enterstate) == strtolower('DAMAN')){

                $state = 'DAMAN & DIU';

            }
            else if(strtolower($enterstate) == strtolower('ARUNACHAL PRADESH') || strtolower($enterstate) == strtolower('ARUNACHALPRADESH')){

                $state = 'ARUNACHAL PRADESH';

            }
            else if(strtolower($enterstate) == strtolower('DADRA NAGAR HAVELI') || strtolower($enterstate) == strtolower('DADRANAGARHAVELI') || strtolower($enterstate) == strtolower('DADRANAGAR HAVELI')){

                $state = 'DADRA NAGAR HAVELI';

            }
            else if(strtolower($enterstate) == strtolower('HIMACHAL PRADESH') || strtolower($enterstate) == strtolower('HIMACHALPRADESH')){

                $state = 'HIMACHAL PRADESH';

            }
            else if(strtolower($enterstate) == strtolower('MIG STATE CODE') || strtolower($enterstate) == strtolower('MIGSTATECODE') || strtolower($enterstate) == strtolower('MIGSTATE CODE')){

                $state = 'MIG STATE CODE';

            }
            else if(strtolower($enterstate) == strtolower('NEW DELHI') || strtolower($enterstate) == strtolower('NEWDELHI')){

                $state = 'NEW DELHI';

            }
            else if(strtolower($enterstate) == strtolower('GUJARAT GLASS LTD.') || strtolower($enterstate) == strtolower('GUJARATGLASSLTD.') || strtolower($enterstate) == strtolower('GUJARAT GLASS LTD') || strtolower($enterstate) == strtolower('GUJARATGLASS LTD.') || strtolower($enterstate) == strtolower('GUJARATGLASS LTD')){

                $state = 'GUJARAT GLASS LTD.';

            }
            else if(strtolower($enterstate) == strtolower('TAMIL NADU') || strtolower($enterstate) == strtolower('TAMILNADU')){

                $state = 'TAMIL NADU';

                 

            }
            else if(strtolower($enterstate) == strtolower('UTTAR PRADESH') || strtolower($enterstate) == strtolower('UTTARPRADESH')){

                $state = 'UTTAR PRADESH';

            }
            else if(strtolower($enterstate) == strtolower('WEST BENGAL') || strtolower($enterstate) == strtolower('WESTBENGAL')){

                $state = 'WEST BENGAL';

            }
            else{
                $state = str_replace(' ', '', $enterstate);
            }
            
            $element->sendKeys($state);
            
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.country"));
            $element->sendKeys('IN');


            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.country"));
            $element->sendKeys('INDIA');
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.zip"));
            $element->sendKeys($row['PIN']);

            //$element = $driver->findElement(WebDriverBy::name("3_AccountBO.Address.Start_Date"))->clear();
            //$myDate = date('d/m/Y');
            //$element->sendKeys();
        }
        $driver->findElement(WebDriverBy::name("Save"))->click();
        $driver->switchTo()->window($handle[0]);

        //PHONE
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Phone Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::id("fnttpagePhone"))->click();
        sleep(5);
        //$driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType")));
        $element->selectByValue('CELLPH');

       sleep(2);
       $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1"))
            );

        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1")));
        $element->selectByValue('REGEML');

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Add Phone and E-mail"))
        );
        $driver->findElement(WebDriverBy::name("Add Phone and E-mail"))->click();
        sleep(5);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        //$driver->switchTo()->defaultContent();      
         if(!empty($row['MOBILENO'])){
           $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType")));
            $element->selectByValue('CELLPH');
            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneNo.cntrycode"));
            $element->sendKeys('91');
            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneNo.localcode"));
            $element->sendKeys($row['MOBILENO']);  
        }
        $driver->findElement(WebDriverBy::name("Save"))->click();
        
        //Email Add
        $driver->switchTo()->window($handle[0]);
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Email Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);

       //start mail
        $driver->findElement(WebDriverBy::name("Add Phone and E-mail"))->click();
        sleep(6);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        } 
        if(!empty($row['EMAILID'])){
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneOrEmail"))
            );

            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneOrEmail")));
            $element->selectByValue('EMAIL');
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1")));
            $element->selectByValue('REGEML');
             $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.Email"))
            );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.Email"));
            $element->sendKeys($row['EMAILID']);   
        }

        $driver->findElement(WebDriverBy::name("Save"))->click();
        sleep(2);
        $driver->switchTo()->window($handle[0]);

        //IDENTIFICATION DETAILS
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Identification Frame Not Found", $driver);
        }

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tab_tpageCont5"))
        );

        $driver->findElement(WebDriverBy::id("tab_tpageCont5"))->click();
        if(!empty($pan)){
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AddIdentificationDetails"))
            );
            $driver->findElement(WebDriverBy::name("AddIdentificationDetails"))->click();
            sleep(3);
            $handle = $this->windowcounts($driver,2);
            if(count($handle) == 2){
                $driver->switchTo()->window(end($handle));
            }
            sleep(7);
            $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("EntityDocumentBO.DocTypeCode"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("EntityDocumentBO.DocTypeCode")));
            $element->selectByValue('IDENTIFICATION');
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("EntityDocumentBO.DocCode")));
            $element->selectByValue('PANGAR');
            $element = $driver->findElement(WebDriverBy::name("EntityDocumentBO.ReferenceNumber"));
            $element->sendKeys($pan);
        }

        $driver->findElement(WebDriverBy::name("SAVE"))->click();
        

        //CURRENCY TAB
        sleep(3);
        $handle = $driver->getWindowHandles();
        $driver->switchTo()->window($handle[0]);
        $driver->switchTo()->defaultContent();
        try {
            $this->switchframeCust($driver);
        } catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Currency Frame Not Found", $driver);
        }
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
        $css = "input[name='radio1']";
        $driver->findElement(WebDriverBy::cssSelector($css))->click();        
        sleep(2);

        /*try {
            $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
            $driver->switchTo()->alert()->accept();
        } catch (Exception $e) {

        }*/

        
        $driver->findElement(WebDriverBy::id("tab_tpageCont6"))->click();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("ADD_CURRENCYDET"))
        );
        $driver->findElement(WebDriverBy::name("ADD_CURRENCYDET"))->click();
        sleep(6);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        sleep(3);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("PsychographicBO.MiscellaneousInfo.strText10"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("PsychographicBO.MiscellaneousInfo.strText10")));
        $element->selectByValue('INR');
        sleep(1);
        $driver->findElement(WebDriverBy::name("SAVE"))->click();
    }

    public function editcust($driver,$row,$cfid, $dob, $name, $pan,$userid,$num){
        if($num==1){
            $driver->switchTo()->defaultContent();
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            );
            $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            $driver->switchTo()->frame($frame);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("appSelect"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("appSelect")));
            $element->selectByValue('CRMServer');
            $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
            $driver->switchTo()->alert()->accept();
            $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("ScreensTOCFrm"))
            );
            $frame = $driver->findElement(WebDriverBy::name("ScreensTOCFrm"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Functionmain"))
            );
            $frame = $driver->findElement(WebDriverBy::name("Functionmain"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("screen1"))
            );
            $driver->findElement(WebDriverBy::id("screen1"))->click();
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("1504"))
            );
            $frame = $driver->findElement(WebDriverBy::name("1504"));
            $driver->switchTo()->frame($frame);
            $driver->wait(30,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("view0"))
            );
            $driver->findElement(WebDriverBy::id("view0"))->click();
        }
        $driver->switchTo()->defaultContent();
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::xpath("html/frameset/frame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::id("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("FilterForm"))
        );
        $frame = $driver->findElement(WebDriverBy::id("FilterForm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("FilterParam1"))
        );
        $element = $driver->findElement(WebDriverBy::name("FilterParam1"))->clear();
        $element->sendKeys($row[$cfid]);
        $driver->switchTo()->defaultContent();
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::xpath("html/frameset/frame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::id("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(30,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("FilterForm"))
        );
        $frame = $driver->findElement(WebDriverBy::id("FilterForm"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::name("submitBut"))->click();
        $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();
        $driver->switchTo()->defaultContent();
        try {
            $this->swithtoframeedit($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT $cfid",$driver);
        }
        $selector = WebDriverBy::xpath('//*[@id="RecordSet"]/tbody/tr[3]/td[1]');
        $driver->wait(30, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        $driver->findElement($selector)->click();
        $script = 'document.getElementById("ie5submenu3").style.visibility = "visible";';
        $driver->executeScript($script);
        $driver->findElement(WebDriverBy::id("suboptions5"))->click();

       

       try {
            $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
            $driver->switchTo()->alert()->accept();

            try {
                    $this->logout($driver,$row['TRNREFNO']);
                } catch (Exception $e) {
                    $this->addException($e, $row['TRNREFNO'], $userid, "Finacle Log Out Error", $driver);
                }
                $driver->quit();
                try {
                    $this->closeIESessions();
                } catch (Exception $e) {
                    
                }

        } catch (Exception $e) {    
        }
                 
       
            sleep(7);
            $handle = $this->windowcounts($driver,2);
            if(count($handle) == 2){
                $driver->switchTo()->window(end($handle));
            }
            $tryCount = 0;
            $this->moreInfoContainer($driver,$tryCount);
            $driver->switchTo()->defaultContent();
            sleep(5);
            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
            );
            $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
            $driver->switchTo()->frame($frame);

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
            );
            $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
            $driver->switchTo()->frame($frame);
             
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
            );
            $frame = $driver->findElement(WebDriverBy::id("userArea"));
            $driver->switchTo()->frame($frame);
            sleep(2);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("IFrmtab0"))
            );
            $frame = $driver->findElement(WebDriverBy::id("IFrmtab0"));
            $driver->switchTo()->frame($frame);

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("formDispFrame"))
            );
            $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
            $driver->switchTo()->frame($frame);

            try {
                $this->editgeneralentry($driver, $row, $cfid, $dob, $name, $pan,$userid);
            }
            catch (Exception $e) {
               $this->addException($e, $row['TRNREFNO'], $userid, "EDIT $cfid",$driver);
           }

        

    }
    public function swithtoframeedit($driver){
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CRMServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CRMServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("DataAreaFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("DataAreaFrm"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::xpath("html/frameset/frame"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::id("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::id("IFrmtab0"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("primaryUserArea"))
        );
        $frame = $driver->findElement(WebDriverBy::name("primaryUserArea"));
        $driver->switchTo()->frame($frame);
    }
    public function swithtoframeeditnext($driver){

        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("formDispFrame"))
        );
        $frame = $driver->findElement(WebDriverBy::id("formDispFrame"));
        $driver->switchTo()->frame($frame);
    }
    public function editgeneralentry($driver, $row, $cfid, $dob, $name, $pan){

        if(!empty($name)){
            
            $exp = explode(' ',trim($name));

            $tnamecount = count($exp);

            $firstname='';
            $middlename='';
            $lastname='';
            if($tnamecount == 3){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = '';
                $lastname = trim($exp[2]);


            }
            elseif($tnamecount == 4){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = trim($exp[2]);
                $lastname = trim($exp[3]);

            }elseif($tnamecount == 5){

                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]).' '.trim($exp[2]);
                $middlename = trim($exp[3]);
                $lastname = trim($exp[4]);

            }
            if($salutaion =="MR" || $salutaion =="MR."){
                $salutaion = "MR";
                $gender = 'M';
            }else{
                $salutaion = "MRS";
                $gender = 'F';
            }
            if($middlename !=''){
                $newname = $salutaion.' '.$firstname.' '.$middlename.' '.$lastname;
            }else{
                $newname = $salutaion.' '.$firstname.' '.$lastname;
            }

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Gender"))
            );

            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.Gender")));
            $element->selectByValue($gender);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Salutation_code"))->clear();
            $element->sendKeys($salutaion);
            sleep(1);
            $element->sendKeys(array(WebDriverKeys::TAB));
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_First_Name"))->clear();
            $element->sendKeys($firstname);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_Middle_Name"))->clear();
            $element->sendKeys($middlename);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Cust_Last_Name"))->clear();
            $element->sendKeys($lastname);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.short_name"))->clear();
            $element->sendKeys($firstname);
            if(!empty($dob)){
                $element = $driver->findElement(WebDriverBy::name("3_AccountBO.Cust_DOB"))->clear();
                $element->sendKeys($dob);
            }
            $driver->findElement(WebDriverBy::id("rownative2"))->click();
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.IntroducerSalutation")));
            $element->selectByValue($salutaion);
            $element = $driver->findElement(WebDriverBy::name("AccountBO.IntroducerName"))->clear();
            $element->sendKeys($newname);
        }
        //Address Details
        $driver->switchTo()->defaultContent(); 
        try {
            $this->swithtoframeeditnext($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT Address Frame of $cfid Not Found",$driver);
        }
        $driver->findElement(WebDriverBy::id("tab_tpageCont3"))->click();
        $selector = WebDriverBy::xpath('//*[@id="RecordSet"]/tbody/tr[3]/td[7]/input');
        $driver->wait(60, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        $driver->findElement($selector)->click();
        sleep(5);
        $handle = $this->windowcounts($driver,3);
        if(count($handle) == 3){
            $driver->switchTo()->window(end($handle));
        }
        sleep(8);
        $tryCount = 0;
        $this->moreInfoContainer($driver,$tryCount);
        if(!empty($row['ADD1'])){
            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.address_Line1"))
             );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.address_Line1"))->clear();
            $element->sendKeys($row['ADD1']);
        }

        if(!empty($row['ADD2'])){
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.Address.address_Line2"))
            );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.address_Line2"))->clear();
            $element->sendKeys($row['ADD2']);

            /*$driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Cat_AccountBO.Address.city"))
            );
            $getcitytext = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.city"))->getText();
            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.city"))->clear();
            $element->sendKeys($row['CITY']);*/
            

            /*if($getcitytext != $row['CITY']){
                //$element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.city"))->clear();
                //$element->sendKeys($row['CITY']);
                $element->sendKeys(array(WebDriverKeys::TAB));
                try {
                $driver->wait(10)->until(WebDriverExpectedCondition::alertIsPresent());
                    $driver->switchTo()->alert()->dismiss();
                } catch (Exception $e) {    
                }
                $driver->findElement(WebDriverBy::name("btnone_AccountBO.Address.city"))->click();
                $this->edit_city_state($driver,$row['CITY'],'1');
            }

            $getstatetext = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.state"))->getText();
            $element = $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.state"))->clear();
            

            if($getstatetext != $row['STATE']){
                
                $element->sendKeys(array(WebDriverKeys::TAB));
                try {
                $driver->wait(10)->until(WebDriverExpectedCondition::alertIsPresent());
                    $driver->switchTo()->alert()->dismiss();
                } catch (Exception $e) {    
                }
                $driver->findElement(WebDriverBy::name("Cat_AccountBO.Address.state"))->click();
                $this->edit_city_state($driver,$row['STATE'],'2');
            }
            $element = $driver->findElement(WebDriverBy::name("AccountBO.Address.zip"))->clear();
            $element->sendKeys($row['PIN']);*/

        }

        $driver->findElement(WebDriverBy::name("Save"))->click();
        $driver->switchTo()->window($handle[1]);

        //PHONE
        $driver->switchTo()->defaultContent();
        try {
            $this->swithtoframeeditnext($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT Phone Frame of $cfid Not Found",$driver);
        }
        $driver->findElement(WebDriverBy::id("fnttpagePhone"))->click();

        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType")));
        $element->selectByValue('CELLPH');

        

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1"))
        );

        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1")));
        $element->selectByValue('REGEML');
       

        $selector = WebDriverBy::xpath('//*[@id="PhoneEmailRecordSet"]/tbody/tr[3]');
        $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        $driver->findElement($selector)->click();

        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Delete Phone and E-mail"))
        );

        $driver->findElement(WebDriverBy::name("Delete Phone and E-mail"))->click();

        $driver->wait(60,1000)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();

        

        try{
            $selector = WebDriverBy::xpath('//*[@id="PhoneEmailRecordSet"]/tbody/tr[3]');
            $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
            $driver->findElement($selector)->click();

            $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("Delete Phone and E-mail"))
            );

            $driver->findElement(WebDriverBy::name("Delete Phone and E-mail"))->click();

            $driver->wait(60,1000)->until(WebDriverExpectedCondition::alertIsPresent());
            $driver->switchTo()->alert()->accept();
        }catch (Exception $e) {
            $this->addException($e, $row['TRNREFNO'], $userid, "Delete Phone and E-mail For EDIT",$driver);
        }       

        $driver->findElement(WebDriverBy::name("Add Phone and E-mail"))->click();

        // $selector = WebDriverBy::xpath('//*[@id="PhoneEmailRecordSet"]/tbody/tr[3]/td[7]/input');
        // $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        // $driver->findElement($selector)->click();
        sleep(6);
        $handle = $this->windowcounts($driver,3);
        if(count($handle) == 3){
            $driver->switchTo()->window(end($handle));
        }
        if(!empty($row['MOBILENO'])){
            sleep(2);

            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType")));
            $element->selectByValue('CELLPH');

            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneNo.cntrycode"))->clear();
            $element->sendKeys('91');
            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneNo.localcode"))->clear();
            $element->sendKeys($row['MOBILENO']);  
        }
        $driver->findElement(WebDriverBy::name("Save"))->click();

        //Email Add
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        $driver->switchTo()->defaultContent();
        try {
            $this->swithtoframeeditnext($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT Email Frame of $cfid Not Found",$driver);
        }


        $driver->findElement(WebDriverBy::name("Add Phone and E-mail"))->click();

        // $selector = WebDriverBy::xpath('//*[@id="PhoneEmailRecordSet"]/tbody/tr[4]/td[7]/input');
        // $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        // $driver->findElement($selector)->click();
        sleep(6);
        $handle = $this->windowcounts($driver,3);
        if(count($handle) == 3){
            $driver->switchTo()->window(end($handle));
        }
        if(!empty($row['EMAILID'])){
             sleep(4);
             
             $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneOrEmail"))
            );

            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneOrEmail")));
            $element->selectByValue('EMAIL');
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.PhoneEmailType1")));
            $element->selectByValue('REGEML');
             $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("AccountBO.PhoneEmail.Email"))
            );
            $element = $driver->findElement(WebDriverBy::name("AccountBO.PhoneEmail.Email"));
            $element->sendKeys($row['EMAILID']);   
 
        }        
        sleep(2);
        $driver->findElement(WebDriverBy::name("Save"))->click();
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        $driver->switchTo()->defaultContent();
        try {
            $this->swithtoframeeditnext($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT $cfid",$driver);
        }

        //IDENTIFICATION DETAILS
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tab_tpageCont5"))
        );
        $driver->findElement(WebDriverBy::id("tab_tpageCont5"))->click();
        //sleep(2);
        /*if(!empty($pan)){
            $selector = WebDriverBy::xpath('//*[@id="EDocRecordSet"]/tbody/tr[3]/td[8]/input');
            $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
            $driver->findElement($selector)->click();

            sleep(2);
            $handle = $this->windowcounts($driver,3);
            if(count($handle) == 3){
                $driver->switchTo()->window(end($handle));
            }
            
            $element = $driver->findElement(WebDriverBy::name("EntityDocumentBO.ReferenceNumber"))->clear();
            $element->sendKeys($pan);
        }*/
        //$driver->findElement(WebDriverBy::name("SAVE"))->click();

        //CURRENCY TAB
        sleep(2);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        $driver->switchTo()->defaultContent();
        try {
            $this->swithtoframeeditnext($driver);
        }
        catch (Exception $e) {
           $this->addException($e, $row['TRNREFNO'], $userid, "EDIT Currency Frame of $cfid Not Found",$driver);
        }
        $driver->findElement(WebDriverBy::id("tab_tpageCont6"))->click();
        $selector = WebDriverBy::xpath('//*[@id="CurrencyDetRecordSet"]/tbody/tr[3]/td[7]/input');
        $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        $driver->findElement($selector)->click();
        sleep(2);
        $handle = $this->windowcounts($driver,3);
        if(count($handle) == 3){
            $driver->switchTo()->window(end($handle));
        }
        sleep(5);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("PsychographicBO.MiscellaneousInfo.strText10"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("PsychographicBO.MiscellaneousInfo.strText10")));
        $element->selectByValue('INR');
        $driver->findElement(WebDriverBy::name("SAVE"))->click();
        sleep(2);
        $driver->switchTo()->window($handle[1]);
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tempFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::name("tempFrm"));
        $driver->switchTo()->frame($frame);
        $frame = $driver->findElement(WebDriverBy::name("buttonFrm"));
        $driver->switchTo()->frame($frame);
        $driver->findElement(WebDriverBy::id("saveBut"))->click();
        $driver->wait(120,1000)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();
        $this->editCustID($trnrefno,$cfid);
    }

    public function moreInfoContainer($driver,$tryCount=0){

            /*while($tryCount < 3){
                try { 
                    sleep(2);
                    $success='';
                    if($tryCount == 2){
                        $success = $driver->executeScript("javascript:expandCollapse('infoBlockID', true)");
                    }
                    if($success == null){
                        break; 
                    }else{
                       $driver->executeScript("document.getElementById('moreInfoContainer').value() = 'moreInfoContainer';");
                       $driver->executeScript("document.getElementById('moreInfoContainer').click();");
                       break;
                    }

                } catch (Exception $e) {
                    $tryCount++;
                }
            }*/
            //$driver->switchTo()->defaultContent();
            $driver->switchTo()->defaultContent();
            sleep(2);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("moreInfoContainer"))
            );


            $driver->executeScript("javascript:expandCollapse('infoBlockID', true)");

            $driver->executeScript("document.getElementById('overridelink').click();");
                //$driver->findElement(WebDriverBy::id("overridelink"))->click();
    }

    public function edit_city_state($driver,$val,$repeat){
        sleep(4);
        if($repeat == '1'){
            $handle = $this->windowcounts($driver,4);
            if(count($handle) == 4){
                $driver->switchTo()->window(end($handle));
            }
            $tryCount = 0;
            $this->moreInfoContainer($driver,$tryCount);
            sleep(3);
            $driver->switchTo()->window(end($handle))->close();
            sleep(2);
            $handle = $this->windowcounts($driver,3);
            if(count($handle) == 3){
                $driver->switchTo()->window(end($handle));
            }
            $driver->findElement(WebDriverBy::name("btnone_AccountBO.Address.city"))->click();
            sleep(2);
            $handle = $this->windowcounts($driver,4);
            if(count($handle) == 4){
                $driver->switchTo()->window(end($handle));
            }
        }else{
            sleep(2);
            $handle = $this->windowcounts($driver,4);
            if(count($handle) == 4){
                $driver->switchTo()->window(end($handle));
            }
        }
        
        sleep(5);
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("SearchOptions"))
        );
        $frame = $driver->findElement(WebDriverBy::name("SearchOptions"));
        $driver->switchTo()->frame($frame);
        
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tabContentFrm"))
        );
        $frame = $driver->findElement(WebDriverBy::id("tabContentFrm"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("userArea"))
        );
        $frame = $driver->findElement(WebDriverBy::id("userArea"));
        $driver->switchTo()->frame($frame);

        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("IFrmtab0"))
        );
        $frame = $driver->findElement(WebDriverBy::name("IFrmtab0"));
        $driver->switchTo()->frame($frame);

        $element = $driver->findElement(WebDriverBy::name("FilterParam1"))->clear();
        $element->sendKeys($val);  

        $driver->findElement(WebDriverBy::name("Submit"))->click();

        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("SearchResult"))
        );
        $frame = $driver->findElement(WebDriverBy::name("SearchResult"));
        $driver->switchTo()->frame($frame);

        $selector = WebDriverBy::xpath('//*[@id="RecordSet"]/tbody/tr[3]');

        $driver->wait(60, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));

        $driver->findElement($selector)->click(); 

        $driver->switchTo()->window(end($handle))->close();
        sleep(2);
        $driver->switchTo()->window($handle[2]);
        $driver->switchTo()->defaultContent(); 
    }

    public function accountcreate($driver,$userId,$row,$trnrefno,$fincoreload){
        if(!empty($row['cifid_1'])){
            try {
                $this->accountcreation($driver, $row,'cifid_1',$trnrefno,$fincoreload);
            } catch (Exception $e) {
                $this->addException($e, $row['TRNREFNO'], $userId, "Primary Account Creation", $driver);
            }
        }
    }

    public function accountcreation($driver,$row,$userId,$trnrefno,$fincoreload){

        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
         );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        sleep(2);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("appSelect"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("appSelect")));
        $element->selectByValue('CoreServer');
        $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CoreServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("FINW"))
        );
        $frame = $driver->findElement(WebDriverBy::id("FINW"));
        $driver->switchTo()->frame($frame);
        sleep(1);
        //echo count($driver->findElements(WebDriverBy::name("tdacop.cifId")));

        /*$driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("menutree"))
        );

        echo count($driver->findElements(WebDriverBy::id("menutree")));*/

        if($fincoreload != 'true'){

	        $driver->wait(60,1000)->until(
	            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("menuName"))
	        );
	        $element = $driver->findElement(WebDriverBy::id("menuName"));
	        $element->sendKeys("HOAACTD");

	        $driver->executeScript('document.getElementById("menuName").value = "HOAACTD";');
	        //sleep(1);
	        $element->sendKeys(array(WebDriverKeys::TAB));
	        try {
	            $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
	            $driver->switchTo()->alert()->accept();
	        } catch (Exception $e) { 
	        }
	        sleep(1);
	        $driver->executeScript("document.getElementById('gotomenu').click();");

	        try {
	            $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
	            $driver->switchTo()->alert()->accept();
	        } catch (Exception $e) {    
	        }
        }
        sleep(6);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tdacop.cifId"))
        );

        $driver->findElement(WebDriverBy::name("tdacop.cifId"))->sendKeys($row['cifid_1']);

        $Ctype = $row['SUBTYPE'];        
        $x =$row['DOB'];
        $dobexp = explode('/', $x);
        $day = $dobexp[0];
        $month =$dobexp[1];
        $year = $dobexp[2];
        $from = new DateTime($year.'-'.$month.'-'.$day);
        $to   = new DateTime('today');
        $age = $from->diff($to)->y;
        $status=$row['STATUS'];

        if(($Ctype=='C') && ($age < '60') && ($status=='R')){
            $schmcode='CEDEP';
            $ledgercode='15050';
        }
        elseif(($Ctype=='C') && ($age >= '60') && ($status=='R')){
            $schmcode='SRCED';
            $ledgercode='15050';
        }
        elseif(($Ctype=='Y') && ($age < '60') && ($status=='R')){
            $schmcode='FEYRL';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Y') && ($age >= '60') && ($status=='R')){
            $schmcode='FEYRS';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Q') && ($age < '60') && ($status=='R')){
            $schmcode='FEQTR';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Q') && ($age >= '60') && ($status=='R')){
            $schmcode='FEQTS';
            $ledgercode='12050';
        }
        elseif(($Ctype=='M') && ($age < '60') && ($status=='R')){
            $schmcode='FEMNT';
            $ledgercode='12050';
        }
        elseif(($Ctype=='M') && ($age >= '60') && ($status=='R')){
            $schmcode='FEMNS';
            $ledgercode='12050';
        }

        elseif(($Ctype=='C') && ($age < '60') && ($status=='NRE')){
            $schmcode='NRCED';
            $ledgercode='15050';
        }
         elseif(($Ctype=='C') && ($age >= '60') && ($status=='NRE')){
            $schmcode='NRECS';
            $ledgercode='15050';
        }
        elseif(($Ctype=='Y') && ($age < '60') && ($status=='NRE')){
            $schmcode='NREYL';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Y') && ($age >= '60') && ($status=='NRE')){
            $schmcode='NREYS';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Q') && ($age < '60') && ($status=='NRE')){
            $schmcode='NREQT';
            $ledgercode='12050';
        }
        elseif(($Ctype=='Q') && ($age >= '60') && ($status=='NRE')){
            $schmcode='NREQS';
            $ledgercode='12050';
        }
        elseif(($Ctype=='M') && ($age < '60') && ($status=='NRE')){
            $schmcode='NREMT';
            $ledgercode='12050';
        }
        elseif(($Ctype=='M') && ($age >= '60') && ($status=='NRE')){
            $schmcode='NREMS';
            $ledgercode='12050';
        }

        if(!empty($row['NAME'])){
            $exp = explode(' ',trim($row['NAME']));

            $tnamecount = count($exp);

            if($tnamecount == 3){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = '';
                $lastname = trim($exp[2]);

            }
            elseif($tnamecount == 4){
                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]);
                $middlename = trim($exp[2]);
                $lastname = trim($exp[3]);

            }elseif($tnamecount == 5){

                $salutaion = trim($exp[0]);
                $firstname = trim($exp[1]).' '.trim($exp[2]);
                $middlename = trim($exp[3]);
                $lastname = trim($exp[4]);

            }
        }
        $driver->findElement(WebDriverBy::name("tdacop.schmCode"))->sendKeys($schmcode);
        $driver->findElement(WebDriverBy::name("tdacop.glSubHeadCode"))->sendKeys($ledgercode);
        $driver->findElement(WebDriverBy::id("Accept"))->click();
        sleep(4);

        //GENERAL TAB DETAILS
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tdgen.acctName"))
        );
        $driver->findElement(WebDriverBy::name("tdgen.acctName"))->clear();
        $driver->findElement(WebDriverBy::name("tdgen.acctName"))->sendKeys($row['NAME']);
        $driver->findElement(WebDriverBy::name("tdgen.acctShortName"))->clear();
        $driver->findElement(WebDriverBy::name("tdgen.acctShortName"))->sendKeys($firstname);
        //use date from 
        $myDate = date('d/m/Y');
        $driver->findElement(WebDriverBy::name("tdgen.acctOpenDate_ui"))->sendKeys($myDate);
        $driver->findElement(WebDriverBy::name("tdgen.modeOfOperCode"))->sendKeys('SING');
        $driver->findElement(WebDriverBy::name("tdgen.locationCode"))->sendKeys('600');

        sleep(2);
        //SCHEME TAB DETAILS
        $driver->findElement(WebDriverBy::id("tdsch"))->click();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("tdsch.valueDate_ui"))
        );
        // date should be taken from excelsheet name
        $driver->findElement(WebDriverBy::name("tdsch.valueDate_ui"))->clear()->sendKeys($row['filename']);
        $driver->findElement(WebDriverBy::name("tdsch.depAmt"))->clear();
        $driver->findElement(WebDriverBy::name("tdsch.depAmt"))->sendKeys($row['AMOUNT']);
        $driver->findElement(WebDriverBy::name("tdsch.depPerdMths"))->clear();
        $driver->findElement(WebDriverBy::name("tdsch.depPerdMths"))->sendKeys($row['TENURE']);
        $driver->findElement(WebDriverBy::name("tdsch.repayAcct"))->sendKeys('10001RPAYAC01');
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("nomineeFlg"))
        );
        if(!empty($row['NNAME'])){
        $css = "input[id='nomineeFlg'][value='Y']";
        $driver->findElement(WebDriverBy::cssSelector($css))->click();
        $driver->findElement(WebDriverBy::cssSelector($css))->click();
        sleep(2);
        }
        if(empty($row['NNAME'])){
        $css = "input[id='nomineeFlg'][value='N']";
        $driver->findElement(WebDriverBy::cssSelector($css))->click();
        $driver->findElement(WebDriverBy::cssSelector($css))->click();
        sleep(2);
        }
        //Interest & Taxes
        $driver->findElement(WebDriverBy::id("tdint"))->click();
        sleep(2);
        $interestrate = $driver->findElement(WebDriverBy::id("contractIntRate"))->getAttribute("value") ;
       
        sleep(2);
        //FLOW TAB DETAILS
        $driver->findElement(WebDriverBy::id("tdflw"))->click();
        sleep(2);
        $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();
        $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("flow_NextRec"))
        );
        $driver->findElement(WebDriverBy::id("flow_NextRec"))->click();
        sleep(3);
        $driver->findElement(WebDriverBy::id("anc_xpld"))->click();
        sleep(5);
        $handle = $this->windowcounts($driver,2);
        if(count($handle) == 2){
            $driver->switchTo()->window(end($handle));
        }
        //verify interest rate 
        //$driver->switchTo()->defaultContent();
        sleep(2);
        $selector = WebDriverBy::xpath('/html/body/form/span/table[2]/tbody/tr/td/table/tbody/tr[2]/td[2]');
        $driver->wait(60, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated($selector));
        $flowrate = $driver->findElement($selector)->getText();        

        $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("back"))
        );
        sleep(2);
        $driver->findElement(WebDriverBy::name("back"))->click();
        sleep(1);
        $handle = $this->windowcounts($driver,1);
        if(count($handle) == 1){
            $driver->switchTo()->window(end($handle));
        }
        $driver->switchTo()->defaultContent();
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
         );
        $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
        $driver->switchTo()->frame($frame);
        /*$driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("appSelect"))
        );
        $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("appSelect")));
        $element->selectByValue('CoreServer');
        $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
        $driver->switchTo()->alert()->accept();*/
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CoreServer"))
        );
        $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
        $driver->switchTo()->frame($frame);
        $driver->wait(60,1000)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("FINW"))
        );
        $frame = $driver->findElement(WebDriverBy::id("FINW"));
        $driver->switchTo()->frame($frame);
        
        if($interestrate == $flowrate){
            //RENEWAL & CLOSURE
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tdren"))
            );
            $driver->findElement(WebDriverBy::id("tdren"))->click();
            sleep(2);
            $css = "input[name='tdren.autoClosureFlg'][value='Y']";
            $driver->findElement(WebDriverBy::cssSelector($css))->click();
            $css1 = "input[name='tdren.autoRenewFlg'][value='N']";
            $driver->findElement(WebDriverBy::cssSelector($css1))->click();
            $driver->findElement(WebDriverBy::id("maxRenew"))->clear();
            $driver->findElement(WebDriverBy::id("renewMths"))->clear();
            $driver->findElement(WebDriverBy::id("renewDays"))->clear();
            $driver->findElement(WebDriverBy::id("renewSchm"))->clear();
            $driver->findElement(WebDriverBy::id("renewGLSubHead"))->clear();
            $driver->findElement(WebDriverBy::id("renewIntTable"))->clear();
            $driver->findElement(WebDriverBy::id("renewCrncy"))->clear();
            //exit();
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("renewOption"))
            );
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::id("renewOption")));
            $element->selectByValue('');

            //NOMINATION DETAILS
             $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("nominationdetails"))
            );
            $driver->findElement(WebDriverBy::id("nominationdetails"))->click();
            sleep(3);
            if(!empty($row['NNAME'])){
                $driver->wait(60,1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("nominationdetails.regValue"))
                );
                $driver->findElement(WebDriverBy::name("nominationdetails.regValue"))->clear();
                $driver->findElement(WebDriverBy::name("nominationdetails.regValue"))->sendKeys($row['APPLNO']);
                $driver->findElement(WebDriverBy::name("nominationdetails.cifId"))->sendKeys($row['cifid_1']);
                $driver->findElement(WebDriverBy::name("nominationdetails.nomName"))->clear();
                sleep(2);
                $driver->findElement(WebDriverBy::name("nominationdetails.nomName"))->clear()->sendKeys($row['NNAME']);
                sleep(1);
                $driver->findElement(WebDriverBy::name("nominationdetails.relation"))->clear();
                $driver->findElement(WebDriverBy::name("nominationdetails.relation"))->sendKeys('OTHERS');
                if(!empty($row['NADD1'])){
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine1"))->clear();
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine1"))->sendKeys($row['NADD1']);
                }
                if(!empty($row['NADD2'])){   
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine2"))->clear();
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine2"))->sendKeys($row['NADD2']);
                }
                /*if(!empty($row['NADD3'])){   
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine3"))->clear();
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomAddrLine3"))->sendKeys($row['NADD3']);
                }*/
                if(!empty($row['NCITY'])){  

                    $sql = "select short_city from city_data where city_name = '".$row['NCITY']."'";
                    $qparent = $this->db->query($sql);

                    //$shortcity = $qparent->query($sql);

                    $rows = $qparent->result_array();
                    if(count($rows) > 0){
                        $shortcity = $rows[0]['short_city'];
                    

                    //$countcity = $qparent->num_rows();
           
                    //if($countcity >0){
                        $driver->findElement(WebDriverBy::name("nominationdetails.nomCityCode"))->clear();
                        $driver->findElement(WebDriverBy::name("nominationdetails.nomCityCode"))->sendKeys($shortcity);
                    }else{
                        $driver->findElement(WebDriverBy::name("nominationdetails.nomCityCode"))->clear();
                        $driver->findElement(WebDriverBy::name("nominationdetails.nomCityCode"))->sendKeys('*');
                    }
                    
                }
                // if(!empty($row['STATE'])){   
                //     $driver->findElement(WebDriverBy::name("nominationdetails.nomStateCode"))->clear();
                //     $driver->findElement(WebDriverBy::name("nominationdetails.nomStateCode"))->sendKeys($row['STATE']);
                // }
                if(!empty($row['NPIN'])){   
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomPostalCode"))->clear();
                    $driver->findElement(WebDriverBy::name("nominationdetails.nomPostalCode"))->sendKeys($row['NPIN']);
                }

                $driver->findElement(WebDriverBy::name("nominationdetails.nomCntryCode"))->clear();
                $driver->findElement(WebDriverBy::name("nominationdetails.nomCntryCode"))->sendKeys('IN');
                sleep(1);
                $driver->findElement(WebDriverBy::name("nominationdetails.nomPcnt"))->clear();
                $driver->findElement(WebDriverBy::name("nominationdetails.nomPcnt"))->sendKeys('100');
                sleep(1);
                $driver->findElement(WebDriverBy::name("nominationdetails.cifId"))->clear();
            }

            //RELATED PARTY
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("relatedpartydetails"))
            );

            $driver->findElement(WebDriverBy::id("relatedpartydetails"))->click();
            sleep(2);
             if(!empty($row['JH1NAME'])){
                $exp = explode(' ',trim($row['JH1NAME']));
                $tnamecount = count($exp);
                if($tnamecount == 3){
                    $salutaion = trim($exp[0]);
                    $firstname = trim($exp[1]);
                    $middlename = '';
                    $lastname = trim($exp[2]);

                }
                elseif($tnamecount == 4){
                    $salutaion = trim($exp[0]);
                    $firstname = trim($exp[1]);
                    $middlename = trim($exp[2]);
                    $lastname = trim($exp[3]);

                }elseif($tnamecount == 5){

                    $salutaion = trim($exp[0]);
                    $firstname = trim($exp[1]).' '.trim($exp[2]);
                    $middlename = trim($exp[3]);
                    $lastname = trim($exp[4]);

                }
            }
     
            if(!empty($row['cifid_2']) && !empty($row['JH1PAN'])){
                $driver->wait(60,1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("relParty_NextRec"))
                );
                $driver->findElement(WebDriverBy::id("relParty_NextRec"))->click();
                sleep(2);
                $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("relatedpartydetails.relnType")));
                $element->selectByValue('J');    
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custTitle"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custTitle"))->sendKeys($salutaion);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custName"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custName"))->sendKeys($firstname.$lastname);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine1"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine1"))->sendKeys($row['NADD1']);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine2"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine2"))->sendKeys($row['NADD2']);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine3"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine3"))->sendKeys($row['NADD3']);
            }

            if(!empty($row['cifid_3']) && !empty($row['JH2PAN'])){
                $driver->wait(60,1000)->until(
                    WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("relParty_NextRec"))
                );
                $driver->findElement(WebDriverBy::id("relParty_NextRec"))->click();
                sleep(2);
                 $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("relatedpartydetails.relnType")));
                $element->selectByValue('J');    
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custTitle"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custTitle"))->sendKeys($salutaion);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custName"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custName"))->sendKeys($firstname.$lastname);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine1"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine1"))->sendKeys($row['NADD1']);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine2"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine2"))->sendKeys($row['NADD2']);
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine3"))->clear();
                $driver->findElement(WebDriverBy::name("relatedpartydetails.custAddrLine3"))->sendKeys($row['NADD3']);
            }

            //DOCUMENT DETAILS
            $driver->findElement(WebDriverBy::id("documentdetails"))->click();
            sleep(3);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("documentdetails.docCode"))
            );
            $driver->findElement(WebDriverBy::name("documentdetails.docCode"))->sendKeys('KYC');
            $element = new WebDriverSelect($driver->findElement(WebDriverBy::name("documentdetails.docScanFlg")));
            $element->selectByValue('Y');
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText1"))->sendKeys($row['text1']);


            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText2"))->sendKeys($row['text2']);
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText3"))->sendKeys($row['text3']);

            // take from gaurav w1234
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText4"))->sendKeys($row['text4']);
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText5"))->sendKeys($row['text5']);
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText6"))->sendKeys($row['text6']);
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText7"))->sendKeys($row['text7']);
            $driver->findElement(WebDriverBy::name("documentdetails.docFreeText10"))->sendKeys($row['text10']);
            
            //OTHER DETAILS
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("otherdetails"))
            );
            $driver->findElement(WebDriverBy::id("otherdetails"))->click();
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("otherdetails.srcDlrId"))
            );
            $driver->findElement(WebDriverBy::name("otherdetails.srcDlrId"))->sendKeys('**C020601');
            sleep(2);
            $driver->findElement(WebDriverBy::id("Submit"))->click();
            $driver->findElement(WebDriverBy::id("Submit"))->click();

           
            sleep(2);
            $handle = $driver->getWindowHandles();
            $driver->switchTo()->window(end($handle));
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("accept"))
            );
            $driver->findElement(WebDriverBy::id("accept"))->click();
            sleep(3);
            $driver->switchTo()->window($handle[0]);
            $driver->switchTo()->defaultContent();
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
             );
            $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            $driver->switchTo()->frame($frame);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("CoreServer"))
            );
            $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
            $driver->switchTo()->frame($frame);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("FINW"))
            );
            $frame = $driver->findElement(WebDriverBy::id("FINW"));
            $driver->switchTo()->frame($frame);
            $driver->wait(60,1000)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("acctNum"))
            );
            $accnum = $driver->findElement(WebDriverBy::id("acctNum"))->getText();
            $this->saveAccountID($accnum,$row['TRNREFNO']);
            $driver->findElement(WebDriverBy::id("Ok"))->click();

            $sql = "UPDATE bot_aps_tracking SET status = 'Y', end_time = '".date("Y-m-d H:i:s")."' WHERE TRNREFNO = '".$row['TRNREFNO']."'";
            $qparent = $this->db->query($sql);            
        }
    }

    /*public function creditdebit($driver){
            
            /*Nomination*/

            // /*$driver->wait(60,1000)->until(
            //     WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("menuName"))
            // );
            // $element = $driver->findElement(WebDriverBy::id("menuName"));
            // $element->sendKeys("HACMTD");

            // $driver->executeScript('document.getElementById("menuName").value = "HACMTD";');
            // $element->sendKeys(array(WebDriverKeys::TAB));
            // sleep(1);
            // $driver->executeScript("document.getElementById('gotomenu').click();");

            // $driver->wait(60,1000)->until(
            // WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("mode"))
            // );

            // $element = new WebDriverSelect($driver->findElement(WebDriverBy::id("mode")));
            // $element->selectByValue('M');


            // $driver->findElement(WebDriverBy::id("tempForacid"))->sendKeys('acoountid');
            // $element->sendKeys(array(WebDriverKeys::TAB));

            // $driver->findElement(WebDriverBy::id("Accept"))->click();*/
            // /*end */

            // $driver->switchTo()->defaultContent();
            // $driver->wait(60,1000)->until(
            //     WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            //  );
            // $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            // $driver->switchTo()->frame($frame);

           


            // /*$driver->switchTo()->defaultContent();
            // $driver->wait(60,1000)->until(
            //     WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            //  );
            // $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            // $driver->switchTo()->frame($frame);

            // $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
            // $driver->switchTo()->frame($frame);

            // sleep(5);
            // $frame = $driver->findElement(WebDriverBy::id("FINW"));
            // $driver->switchTo()->frame($frame);
            // sleep(3);
            // try {
            //         $driver->wait(5)->until(WebDriverExpectedCondition::alertIsPresent());
            //         sleep(1);
            //         $driver->switchTo()->alert()->accept();
            //     } catch (Exception $e) {        
            // }*/

            
            //$driver->executeScript("document.getElementById('anc_Home').click();");

            // $driver->findElement(WebDriverBy::id("anc_Home"))->click();

            // $driver->switchTo()->defaultContent();
            // $driver->wait(60,1000)->until(
            //     WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name("loginFrame"))
            //  );
            // $frame = $driver->findElement(WebDriverBy::name("loginFrame"));
            // $driver->switchTo()->frame($frame);

            // $frame = $driver->findElement(WebDriverBy::name("CoreServer"));
            // $driver->switchTo()->frame($frame);

            // sleep(5);
            // $frame = $driver->findElement(WebDriverBy::id("FINW"));
            // $driver->switchTo()->frame($frame);

            // $driver->wait(60,1000)->until(
            //     WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("menuName"))
            // );

            // $element = $driver->findElement(WebDriverBy::id("menuName"));
            // $element->sendKeys("HTM");
            // $driver->executeScript('document.getElementById("menuName").value = "HTM";');
            // $element->sendKeys(array(WebDriverKeys::TAB));
            // sleep(1);
            // $driver->findElement(WebDriverBy::id("gotomenu"))->click();

            // $driver->wait(60,1000)->until(
            // WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("funcCode"))
            // );

            // $element = new WebDriverSelect($driver->findElement(WebDriverBy::id("funcCode")));
            // $element->selectByValue('A');

            // $driver->wait(60,1000)->until(
            // WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id("tranTypeSubType"))
            // );

            // $element = new WebDriverSelect($driver->findElement(WebDriverBy::id("tranTypeSubType")));
            // $element->selectByValue('T/CI');
            // $driver->findElement(WebDriverBy::id("Go"))->click();

            // $element = $driver->findElement(WebDriverBy::id("acctId"));
            // $element->sendKeys('HFCFDFUNDAC');
            // $element->sendKeys(array(WebDriverKeys::TAB));

            // $driver->findElement(WebDriverBy::id("refCrncy"))->sendKeys('INR');
            // //$row['AMOUNT']
            // $driver->findElement(WebDriverBy::id("refAmt"))->sendKeys('10000');
            // $driver->findElement(WebDriverBy::id("tranParticular"))->sendKeys('150000525299');

            
            // $driver->findElement(WebDriverBy::id("partTranDetail_NextRec"))->click();
            
            // $css = "input[id='pTranType']";
            // $driver->findElement(WebDriverBy::cssSelector($css))->click();  

            // $element = $driver->findElement(WebDriverBy::id("acctId"))->clear();
            // $element->sendKeys('150000525299');    
            // $element->sendKeys(array(WebDriverKeys::TAB));

            // //validate customer name

            // $driver->findElement(WebDriverBy::id("refAmt"))->clear()->sendKeys('10000');
            // $driver->findElement(WebDriverBy::id("tranParticular"))->clear()->sendKeys('HFCFD');

            // //file date
            // $driver->findElement(WebDriverBy::id("valueDate_ui"))->clear()->sendKeys('04-07-2019');

            
            // $driver->findElement(WebDriverBy::id("Save"))->click();
            
    //}

    public function saveAccountID($accnum, $trnrefno){
        $sql = "UPDATE hfccustdata SET AccountNo = $accnum where TRNREFNO = '$trnrefno'";
        $qparent = $this->db->query($sql);
    }

    public function closeIESessions(){
        $wmiLocator = new COM("WbemScripting.SWbemLocator");
        $objWMIService = $wmiLocator->ConnectServer('.', "root/cimv2", '', '');
        $objWMIService->Security_->ImpersonationLevel = 3;
        $oReg = $objWMIService->Get("StdRegProv");
        $compsys = $objWMIService->ExecQuery("Select * from Win32_process where name = 'iexplore.exe' or name = 'IEDriverServer.exe'");
        foreach ( $compsys as $compsys_val)
        {
            $compsys_val->Terminate();
        }
    }

    function takeScreenshot($prefix, $driver, $control, $trnrefno){
        $path = APS_SCREENSHOTS.$trnrefno.SEPARATOR;
        if(!is_dir($path)){
            mkdir($path, 0777, true);
        }
        $img = $prefix.$control->process_dtl_id.'_'.$control->seq_id.'_'.date('Y_m_d_H_i_s').'.png';
        $driver->takeScreenshot($path.$img);


           /* try {
            $this->logout($driver,$trnrefno);
            } catch (Exception $e) {
                $this->addException($e, $trnrefno, $userId, "Finacle Log Out Error", $driver);
            }*/
    }

    public function saveCustID($custid, $trnrefno,$cfid){
       
        $sql = "UPDATE hfccustdata SET $cfid = $custid where TRNREFNO = '$trnrefno'";
        $qparent = $this->db->query($sql);

        $sql = "UPDATE bot_aps_tracking SET is_processed = 'No',end_time = '".date("Y-m-d H:i:s")."' where TRNREFNO = '$trnrefno'";
        $qparent = $this->db->query($sql);

    }

    public function editCustID($trnrefno,$cfid){

        $sql = "UPDATE hfccustdata SET edit_$cfid = '1' where TRNREFNO = '$trnrefno'";
        $qparent = $this->db->query($sql);

        $sql = "UPDATE bot_aps_tracking SET end_time = '".date("Y-m-d H:i:s")."' where TRNREFNO = '$trnrefno'";
        $qparent = $this->db->query($sql);
    }

    public function addException($e, $trnrefno, $userId, $error_section, $driver){

        echo '<pre>';
        print_r($e);

        $img = SCREENSHOTS.$trnrefno.'_'.date('Y_m_d_H_i_s').'.png';

        $error = $e->getResults();
        $exception_dtl = $error['value']['localizedMessage'];

        $exception_dtl = $this->db->escape_str($exception_dtl);
        $exception_class = get_class($e);
        $exception_class = $this->db->escape_str($exception_class);
        $excp = explode('Build info:', $exception_dtl);
        $excp = explode('For documentation', $excp[0]);

        $sql = "INSERT INTO bot_error_logs (exception_class, TRNREFNO, exception_dtl, userId, error_section, screenshot_path) VALUES ('".$exception_class."','".$trnrefno."','".$excp[0]."', '".$userId."', '".$error_section."','".$this->db->escape_str($img)."')";
        $qparent = $this->db->query($sql);

        
        $sql = "UPDATE bot_aps_tracking SET status = 'E', end_time = '".date("Y-m-d H:i:s")."' WHERE TRNREFNO = '".$trnrefno."'";
        $qparent = $this->db->query($sql);

        try {
            $driver->takeScreenshot($img);
        } catch (Exception $e) {
            
        }

        try {
            $this->logout($driver,$trnrefno);
            } catch (Exception $e) {
                $this->addException($e, $trnrefno, $userId, "Finacle Log Out Error", $driver);
            }
        exit();
    }

    public function getUserName(){
        $localIP = getHostByName(getHostName());
        $sql = "SELECT username FROM bot_ip_logins WHERE ip_address = '$localIP'";
        $rows = $this->db->query($sql)->result_array();
        if(count($rows) > 0){
            return $rows[0]['username'];
            //return 'HE000430';
            //return 'HE000352';
            //return 'HE000352';
        }else{
            //return 'HE000430';
            //return 'HE000352';
            //return 'HE000352';
        }
    }

    

    public function getPassword(){
        $localIP = getHostByName(getHostName());
        $sql = "SELECT password FROM bot_ip_logins WHERE ip_address = '$localIP'";
        $rows = $this->db->query($sql)->result_array();
        if(count($rows) > 0){
            
            
            
            //$password = 'priya2019*';
            //$ciphertext = $this->encryption->encrypt($rows[0]['password']);
            //$key = bin2hex($this->encryption->create_key(16));
            //echo $this->decryptd($rows[0]['password'],$key);exit;
           //echo  $this->encryption->decrypt($rows[0]['password']);exit;
            //echo $rows[0]['password'];exit;
            //$key = bin2hex($this->encryption->create_key(16));

            //echo $this->encryption->decrypt($rows[0]['password']);exit;
            //echo $this->decryptdata($rows[0]['password'],$key);

           // echo $this->decryptdata($rows[0]['password'],$key);
            #return $this->decrypt($rows[0]['password'], $key);
            return $rows[0]['password'];

            //return 'hfc@1234';
        }else{
            //return 'hfc@1234';
        }
    }

    public function decryptd($message, $key, $encoded = false)
        {
            
            $method = 'aes-256-ctr';
            if ($encoded) {
                $message = base64_decode($message, true);
                if ($message === false) {
                    throw new Exception('Encryption failure');
                }
            }

            $nonceSize = openssl_cipher_iv_length($method);
            $nonce = mb_substr($message, 0, $nonceSize, '16bit');
            $ciphertext = mb_substr($message, $nonceSize, null, '16bit');
            
            $plaintext = openssl_decrypt(
                $ciphertext,
                $method,
                $key,
                OPENSSL_RAW_DATA,
                $nonce
            );
            
            return $plaintext;
        }
    public function isAlertPresent($driver){
        try {
            $driver->switchTo()->alert()->accept();
            return 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function waitForAjax($driver)
    {
        $code = "return document.readyState";
        do {
        //wait for it
        } while ($driver->executeScript($code) != 'complete');
    }

    public function windowcounts($driver,$num)
    {
        $handle = $driver->getWindowHandles();
        if(count($handle) != $num){
            $i = 1;
            while($i){
                $handle = $driver->getWindowHandles();
                if(count($handle) == $num){
                 break;
                }
                $i++;
            }
        }
        return $handle;
    }
        
 
    public function savepassword($data){
       
        if(!empty($_POST['username']) && !empty($_POST['password'])){
            $ip_address = getHostByName(getHostName());
            $username = $_POST['username'];

            $this->encryption->initialize(
                array(
                        'cipher' => 'aes-256',
                        'mode' => 'ctr',
                        'key' => bin2hex($this->encryption->create_key(16))
                )
            );
        $password = $_POST['password'];
        $ciphertext = $this->encryption->encrypt($password);

        $query = "DELETE FROM `bot_ip_logins` WHERE `ip_address` = '".$ip_address."'";
        $this->db->query($query);

        $query1 = "Insert into bot_ip_logins(`ip_address`,`username`,`password`) values('".$ip_address."','".$username."','".$ciphertext."')";

        $result = $this->db->query($query1);

        return $result;

        // Outputs: This is a plain-text message!
        //echo $this->encryption->decrypt($ciphertext);exit;

        }




    }


}
?>