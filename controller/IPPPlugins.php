<?php
class IPPPlugins
{
    public $available_plugins;
    public $hook_footer;
    public $hook_header;
    public $hook_login;
    public $hook_onboarding;
    public $bookkeeping;
    public $communication;

    function __construct($request) {
        $this->request = $request;
    }
    public function loadPlugins() {
        if ($handle = opendir(BASEDIR . 'plugins')) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != "index.php") {
                    if(file_exists(BASEDIR . "plugins/".$entry."/init.php")) {
                        include(BASEDIR . "plugins/".$entry."/init.php");
                        $this->loadPlugin($entry);
                        if(file_exists(BASEDIR . "plugins/".$entry."/settings.php")) {
                            $settings = [];
                            include(BASEDIR . "plugins/".$entry."/settings.php");
                            if(isset($settings) && count($settings) > 0)
                                $this->setSettingsValues($entry,$settings);
                        }
                    }
                }
            }
            closedir($handle);
        }
        if(is_dir(BASEDIR . "mu-plugins")) {
            if ($handle = opendir(BASEDIR . 'mu-plugins')) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && $entry != "index.php") {
                        include(BASEDIR . "plugins/".$entry."/init.php");
                        $this->loadPlugin($entry);
                        if(file_exists(BASEDIR . "mu-plugins/".$entry."/settings.php")) {
                            $settings = [];
                            include(BASEDIR . "mu-plugins/".$entry."/settings.php");
                            if(isset($settings) && count($settings) > 0)
                                $this->setSettingsValues($entry,$settings);
                        }
                    }
                }
                closedir($handle);
            }
        }
    }

    public function setAvailablePlugin($slug) {
        $this->available_plugins[] = $slug;
    }

    public function setBookeeping($slug) {
        $this->bookkeeping[] = $slug;
    }

    public function getAvailablePlugins($company_plugin=false) {
        $list = $this->available_plugins;
        $plugin_list = [];
        if($company_plugin) {
            foreach($list as $value) {
                if(isset($value->company_plugin)) {
                    $plugin_list[$value->id] = $value;
                }
            }
        } else {
            if(count($list) > 0) {
                foreach($list as $value) {
                    if(!isset($value->company_plugin)) {
                        $plugin_list[$value->id] = $value;
                    }
                }
            }
        }
        return $plugin_list;
    }
    public function plugin_name()
    {
        $this->name();
    }
    public function init()
    {
        if(class_exists($this->initialization()))
            $this->initialization();
    }
    protected function name()
    {
        echo 'The name of each plugin<br />';
    }
    protected function initialization()
    {
        echo 'The plugin initialization<br />';
    }
    public function fields()
    {
        if($this->getFields())
            return $this->getFields();
    }
    public function GetPluginFields($entry,$company_id="")
    {
        $settings = [];
        if($company_id !== "")
            $company_id .= "_";
        if(file_exists(BASEDIR . "plugins/".$entry."/".$company_id."settings.php")) {
            include(BASEDIR . "plugins/".$entry."/".$company_id."settings.php");
        }
        return $settings;
    }
    public function getSettingsFields($plugin_name) {
        global $partner_page;
        if(isset($this->available_plugins[$plugin_name])) {
            if(isset($partner_page) && $partner_page === 1) {
                if(isset($this->available_plugins[$plugin_name]->company_plugin) && $this->available_plugins[$plugin_name]->company_plugin) {
                    $fields = [];
                    $all_fields = $this->available_plugins[$plugin_name]->getFields();
                    if(!is_null($all_fields)) {
                        foreach($all_fields as $value) {
                            if(isset($value["access"]) && $value["access"] === "partner")
                                $fields[] = $value;
                        }
                    }
                } else {
                    $fields = $this->available_plugins[$plugin_name]->getFields();
                }
            } else {
                if(isset($this->available_plugins[$plugin_name]->company_plugin) && $this->available_plugins[$plugin_name]->company_plugin) {
                    $fields = [];
                    $all_fields = $this->available_plugins[$plugin_name]->getFields();
                    foreach($all_fields as $value) {
                        if(!isset($value["access"]) || (isset($value["access"]) && $value["access"] !== "partner"))
                            $fields[] = $value;
                    }
                } else {
                    $fields = $this->available_plugins[$plugin_name]->getFields();
                }
            }
            return json_encode($fields);
        }
        else {
            return "";
        }
    }
    public function checkLatestVersion($request,$entry) {
        $file = BASEDIR . "plugins/".$entry."/version.php";
        if(file_exists( $file)) {
            include_once($file);
            if($version["checked"] < (time()-259200)) {
                $plugin_version = $request->plugins("",["plugin"=>$entry]);
                if($version["version"] !== $plugin_version) {
                    $version["latest"] = 0;
                    $txt = "<?php \n";
                    foreach($version as $key=>$value) {
                        $txt .= "\$version[\"".$key."\"] = '" . $value . "';\n";
                    }
                    $myfile = fopen($file, "w") or die("Unable to open file!");
                    fwrite($myfile, $txt);
                    fclose($myfile);
                }
            }
        } else {
            $version = $request->plugins("",["plugin"=>$entry]);
            $txt = "<?php \n \$version[\"version\"] = '" . $version . "';\n \$version[\"checked\"] = '" . time() . "';\n \$version[\"latest\"] = '1';\n";
            $myfile = fopen($file, "w") or die("Unable to open file!");
            fwrite($myfile, $txt);
            fclose($myfile);
        }
    }
    private function setSettingsValues($plugin_name,$values) {
        if(is_object($this->available_plugins[$plugin_name])) {
            $this->available_plugins[$plugin_name]->values = $values;
        }
        if(method_exists($this->available_plugins[$plugin_name],"hook_footer"))
            $this->hook_footer[] = $this->available_plugins[$plugin_name]->hook_footer();
        if(method_exists($this->available_plugins[$plugin_name],"hook_header"))
            $this->hook_header[] = $this->available_plugins[$plugin_name]->hook_header();
        if(method_exists($this->available_plugins[$plugin_name],"hook_login"))
            $this->hook_login[] = $this->available_plugins[$plugin_name]->hook_login();
    }
    public function updateSettingsValues($plugin_slug,$variable,$content,$action="o") {
        global $partner,$utils;
        $fields = $this->GetPluginFields($plugin_slug);
        if(!isset($fields["plugin_id"])) {
            echo "An unexpected error. Could not identify plugin_id";
            die();
        }
        if(is_array($content) || is_object($content))
            $content = json_encode($content,true);

        if(!isset($fields[$variable]) || $action === "o") {
            $fields[$variable] = $content;
        }
        elseif(isset($fields[$variable]) && $action === "a") {
            if($utils->isJson($content)) {
                $old_fields = json_decode($fields[$variable],false);
                $old_fields[] = (json_decode($content)[0]);
                $fields[$variable] = json_encode($old_fields);
            } else {
                $fields[$variable] .= $content;
            }
        }
        $myfile = fopen(BASEDIR . "plugins/".$plugin_slug."/settings.php", "w") or die("Unable to open file!");
        $txt = "<?php\n";
        fwrite($myfile, $txt);
        foreach($fields as $key=>$value) {
            $partner->UpdatePluginSettings($fields["plugin_id"],$key,$value);
            $txt = "\$settings[\"".$key."\"] = '" . $value . "';\n";
            fwrite($myfile, $txt);
        }
        fclose($myfile);
        $update_plugin = new $plugin_slug();
        if(method_exists($update_plugin,"hookUpdate"))
            $update_plugin->hookUpdate($plugin_slug,$fields["plugin_id"],$fields);

    }
    public function getSettingsValues($plugin_name, $value,$specific_setting_file=false) {
        if(!$specific_setting_file) {
            if(isset($this->values[$value]))
                return $this->values[$value];
            elseif(isset($this->available_plugins[$plugin_name]->values[$value]))
                return $this->available_plugins[$plugin_name]->values[$value];
            elseif(isset($this->available_plugins[$plugin_name]->values))
                return json_encode($this->available_plugins[$plugin_name]->values);
            else
                return "{}";
        } else {
            if(file_exists($specific_setting_file)) {
                include($specific_setting_file);
                if($value !== "")
                    return $settings[$value];
                else
                    return json_encode($settings);
            } else
                return "{}";
        }
    }
    public function getId($plugin_name) {
        return $this->available_plugins[$plugin_name]->id;
    }
    public function getStandardConfigs($plugin_name) {
        $this->setFields();
        $standard_values = [];
        if(is_object($this->fields()) || is_array($this->fields())) {
            foreach($this->fields() as $value) {
                if(isset($value["standard"]))
                    $standard_values[] = $value;
            }
        }
        return $standard_values;
    }
    public function hasExternalLogin($plugin_name) {
        if(
            isset($this->available_plugins[$plugin_name]) &&
            is_object($this->available_plugins[$plugin_name]) &&
            method_exists($this->available_plugins[$plugin_name],"externalLogin"))
            return $this->available_plugins[$plugin_name]->externalLogin();
        else
            return false;

    }
    public function hasExternalCommunication($plugin_name,$method,$request) {
        if(
            isset($this->available_plugins[$plugin_name]) &&
            is_object($this->available_plugins[$plugin_name]) &&
            method_exists($this->available_plugins[$plugin_name],"externalFeedback"))
            return (array)$this->available_plugins[$plugin_name]->externalFeedback($method,$request);
        else
            return [];
    }
    private function loadPlugin($plugin_name) {
        $this->available_plugins[$plugin_name] = new $plugin_name($this->request);

        if(isset($this->available_plugins[$plugin_name]->bookkeeping))
            $this->bookkeeping = $this->available_plugins[$plugin_name]->bookkeeping;

        if(isset($this->available_plugins[$plugin_name]->hook_onboarding))
            $this->hook_onboarding = $this->available_plugins[$plugin_name]->hook_onboarding;

        if(isset($this->available_plugins[$plugin_name]->communication)) {
            if(!is_object($this->communication))
                $this->communication = new stdClass();
            $this->communication->{$plugin_name} = new StdClass();
            $this->communication->{$plugin_name} = $this->available_plugins[$plugin_name]->communication->{$plugin_name};
        }
    }



    public function loadPage($plugin_name,$page,$REQ) {
        $this->available_plugins[$plugin_name] = new $plugin_name($this->request);
        return (array)$this->available_plugins[$plugin_name]->{"pages_".$page}($REQ);
    }








    // BOOKEEPING
    public function ListInvoices() {
        $invoice_lists = new stdClass();
        if(is_array($this->bookkeeping)) {
            foreach($this->bookkeeping as $value) {
                $bookkeeping = new $value();
                $invoices = json_decode(json_encode($bookkeeping->getInvoices()));
                foreach($invoices as $invoice) {
                    $invoice_lists->{$invoice->guid} = $invoice;
                }
            }
        }

        return $invoice_lists;
    }

    public function ListSpecificInvoice($provider,$guid) {
        $bookkeeping = new $provider();
        $data = $bookkeeping->getInvoice($guid);

        return $data;
    }


}
