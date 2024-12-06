// add the 'novalidate' setting to <form> tag
// stackoverflow.com/questions/3090369/
function my_novalidate($form_tag, $form) {

  // collect field types
  $types = array();
  foreach ( $form['fields'] as $field ) {
    $types[] = $field['type'];
  }

  // bail if form doesn't have a website field
  if ( ! in_array('website', $types) )
    return $form_tag;

  // add the 'novalidate' setting to the website <form> element
  $pattern = "#method=\'post\'#i";
  $replacement = "method='post' novalidate";
  $form_tag = preg_replace($pattern, $replacement, $form_tag);

  return $form_tag;
}
add_filter('gform_form_tag','my_novalidate',10,2);

// add "http://" to website if protocol omitted
function my_protocol($form) {

  // loop through fields, taking action if website
  foreach ( $form['fields'] as $field ) {

  // skip if not a website field
  if ( 'website' != $field['type'] )
      continue;

  // retrieve website field value
  $value = RGFormsModel::get_field_value($field);

  // if there is no protocol, add "http://"
  // Recognizes ftp://, ftps://, http://, https://
  // stackoverflow.com/questions/2762061/
  if ( ! empty($value) && ! preg_match("~^(?:f|ht)tps?://~i", $value) ) {
    $value = "http://" . $value;

    // update value in the $_POST array
    $id = (string) $field['id'];
    $_POST['input_' . $id] = $value;
  }
  }

  return $form;
}
add_filter('gform_pre_validation','my_protocol');
