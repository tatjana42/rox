<?php 
$vars = $this->getRedirectedMem('vars');
if (empty($vars)) {
    $vars['activity-radius'] = $this->radius;
}
$radiusSelect = '<select name="activity-radius" id="activity-radius">';
$distance = array(
    0 => '0 km/0 mi', 
    5  => '5 km/3 mi', 
    10 => '10 km/6 mi', 
    25 => '25 km/15 mi', 
    50 => '50 km/30 mi', 
    100 => '100 km/60 mi',
);
foreach($distance as $value => $display) :
    $radiusSelect .= '<option value="' . $value . '"';
    if ($value == $vars['activity-radius']) {
        $radiusSelect .= ' selected="selected"';
    }
    $radiusSelect .= '>' . $display . '</option>';
endforeach;
$radiusSelect .= '</select>';
?>
<div class="col-12">
<form method="POST" name="activity-radius-form">
<?php 
echo $this->layoutkit->formkit->setPostCallback('ActivitiesController', 'setRadiusCallback');
echo $words->get('ActivitiesRadiusSelect', $radiusSelect); ?>
<input type="submit" class="btn btn-primary btn-sm" id="activity-nearme" name="activity-nearme" value="update" />
</form>
</div>
<?php
if (count($this->activities) == 0) {
    echo '<div class="col-12 h4 mt-3">' . $words->get('ActivitiesNoActivitiesNearYou', $vars['activity-radius']) . '</div>';
} else {
    require_once('activitieslist.php');
}
?>
