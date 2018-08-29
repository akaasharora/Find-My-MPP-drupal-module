<?php
/**
 * @file
 * Contains \Drupal\lao_find_my_mpp\Form\LaoFindMyMppForm.
 */
namespace Drupal\lao_find_my_mpp\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LaoFindMyMppForm extends FormBase{
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'lao_find_my_mpp_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['#action']='#block-findmymppblock';
        $session = \Drupal::request()->getSession();
        $error_message=!empty($session->get('find_mpp_error'))?$session->get('find_mpp_error'):'';
        //$error_message = !empty($form_state->getValue('find_mpp.error'))?$form_state->getValue('find_mpp.error'):"";

        $form['error'] = array(
            '#type' => 'markup',
            '#markup' => $error_message,
        );

        $form['location'] = array(
            '#type' => 'textfield',
            '#title' => t('Enter your postal code or address:'),
            '#required' => TRUE,
        );
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Find'),
            '#button_type' => 'primary',
        );

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $session =\Drupal::request()->getSession();
        $error_formatted = "<span class='alert alert-warning'>";
        $error_formatted .= t('Sorry, we could not find postal code or address.');
        $error_formatted .= "</span>";
        $error_formatted .= "<p>";
        $error_formatted .= t("Try entering your full address, including the province and country, for example <em>111 Wellesley St. West, Toronto, Ontario, Canada</em> or see a full <a href='/en/members/current'>list of current MPPs</a>");

        $postal_code = $form_state->getValues()['location'];
        if ($postal_code=='') {
            //drupal_set_message("Cannot be empty! Please enter your Postal Code or Address.");
            $form_state->setValue('find_mpp.error', "no");
        }else{
            $operation = $form_state->getValues()['op']->__toString();
            $geo_code = $this->convertPostalToGeo($postal_code);
            if (!empty($geo_code)){
                $riding_array = $this->getRiding($geo_code);
                if (!empty($riding_array)){
                    $riding = $riding_array[0];// Final Riding - this is the correct riding from open north API
                    if (!empty($riding)){
                        drupal_set_message("You entered " . $postal_code . ". Your Riding is : " . $riding);

                        $member_ids= $this->getActiveMemberIds();

                        \Drupal::logger("member IDs")->notice(" ". json_encode($member_ids));

                        $member_name_array = $this->buildMemberRidingArray($member_ids,'en');

                        $theId= $this->getID($riding,$member_name_array,$member_ids);//Akaash
                        if(!empty($theId)){
                            //drupal_set_message($this->t('You entered: @location ', array('@location ' => $form_state->getValue('location'))));
                            // drupal_set_message("id:". $theId);

                            $session->set('find_mpp_error',"");
                            $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
                            $path = \Drupal::service('path.alias_manager')->getAliasByPath('/node/' . $theId, $language);
                            $response = new RedirectResponse('/' . $language . $path);
                            return $response->send();
                        }else{
                            // we did not find $member_ids
                            $session->set('find_mpp_error',$error_formatted);
                        }

                    }else{
                        // we did not find a riding
                        $session->set('find_mpp_error',$error_formatted);

                    }
                }else{
                    $session->set('find_mpp_error',$error_formatted);
                }


            }else{
                // we could not find the geocode
                $session->set('find_mpp_error',$error_formatted);

            }


        }


    }
    public function replaceDashes($dash_string){
        /**
         * This function replaces the en dash(-) and em dash(—) in riding name with a space.
         * This is done to compare the riding name from Open North's database and our database.
         * example: comparing Spadina—Fort York and Spadina-Fort York.
         */

        $dash_string = str_replace('—',' ', $dash_string);
        $dash_string = str_replace('-',' ',$dash_string);

        return $dash_string;
    }

    public function getID($riding,$member_array,$nid){

        $nodeID=0;
        $riding = $this->replaceDashes($riding);
        foreach ($nid as $id){

            $member_riding = $this->replaceDashes($member_array[$id]['riding']);
            if ($riding == $member_riding){
                $nodeID = $id;
                break;
            }
        }
        return $nodeID;
    }


    public function getActiveMemberIds() {
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        if($language == "en") {
            $active_word = "Active";
        } else {
            $active_word = "Actif";
        }
        $status_tid_en = $this->checkTermByTitle("active","status");
        $query = \Drupal::entityQuery('node')
            ->condition('type', 'member')
            ->condition('field_status_ref', $status_tid_en);
//            ->condition('langcode', $language);
        $nids = $query->execute();
        if (!empty($nids)) {
            return $nids;
        }
        return NULL;
    }

    public function buildMemberRidingArray($nids, $language) {
        $member_array = [];
        foreach ($nids as $nid) {
            \Drupal::logger("member ID being processed")->notice("is " . $nid);

            $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
            if($node->hasTranslation($language)) {
                $node = $node->getTranslation($language);
            }
            $full_name = $node->field_full_name_by_first_name->getValue();
            $riding = $node->field_current_riding->getValue();
            if($riding) {
                $riding = reset($riding);
                $riding_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($riding['target_id']);
                $riding_name = $riding_term->label();
            }

            if(!empty($full_name) && !empty($riding_name)) {
                \Drupal::logger("member being processed")->notice("is " . json_encode($full_name) . " & " . json_encode($riding_name));

                $member_array[$nid] = [
                    'name' => $full_name,
                    'riding' => $riding_name
                ];
            } else {
                \Drupal::logger("member being processed")->notice("something went wrong");
            }
        }
        return $member_array;
    }



    public function checkTermByTitle($title , $type){
        $query = \Drupal::entityQuery('taxonomy_term')
            ->condition('vid',$type)
            ->condition('name', $title);
//            ->condition('langcode', $language);
        $tids = $query->execute();
        if (!empty($tids)) {
            $tids=reset($tids);
            return $tids;
        }
        return NULL;
    }

    public function convertPostalToGeo($postal_code) {

        /**
         *
         * This function will get the values from the text field and convert them into geocode.
         * this will be achieved by using Google's Geocode API.
         * Example - https://maps.googleapis.com/maps/api/geocode/json?address=m5v1b1&key=AIzaSyD0FH0qHadaGu0z63zzfCd_i0Mgb1KCzgU
         * From here we will get Latitude and Longitude.
         *
         */
        $address = urlencode($postal_code);
        $key ='AIzaSyD0FH0qHadaGu0z63zzfCd_i0Mgb1KCzgU';
        $geoQuery = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.$key;
        $content = file_get_contents($geoQuery);
        $content_array = json_decode($content,true);
        if (isset( $content_array['results'][0])) {
            $lat = $content_array['results'][0]['geometry']['location']['lat'];
            $lng = $content_array['results'][0]['geometry']['location']['lng'];
            $ret = 'Lat:' . $lat . " Lng: " . $lng;
            return $lat . ',' . $lng;
        }else{
            return null;
        }
    }
    public function getRiding($geo_code){

        /**
         * This function finds the riding associated with the geocode.
         * This is achived by sending the requesst to Open North's API
         * Example - https://represent.opennorth.ca/boundaries/?contains=43.7733946,-79.4940824
         */

        $contains = urlencode($geo_code);
        $openNorthQuery = 'http://represent.opennorth.ca/boundaries/?contains=' . $contains;
        $query_content = file_get_contents($openNorthQuery);
        $query_content_array = json_decode($query_content, true);
        // $riding = $query_content_array['objects'][0]['name'];

        $riding_array = [];
        $riding = '';
        $ctr = 0;
        for ($j = 0; $j < count($query_content_array['objects']); $j++) {
            if ($query_content_array['objects'][$j]['boundary_set_name'] == 'Ontario electoral district') {

                $riding_array[$ctr] = $query_content_array['objects'][$j]['name'];
                $ctr++;
                //$riding = $query_content_array['objects'][0]['name'];
            }

        }
        return $riding_array;
        //return $riding;
    }
}

