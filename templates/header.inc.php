<?php
use EGroupware\Api;
use EGroupware\SmallParT\Bo;

Api\Framework::includeCSS('/smallpart/css/bootstrap.min.css');
Api\Framework::includeCSS('/smallpart/css/bootstrap-theme.min.css');
Api\Framework::includeCSS('/smallpart/css/style.css');

include("utils/ToLoadScript.php");

Api\Framework::includeJS('/smallpart/js/bootstrap.min.js');
echo $GLOBALS['egw']->framework->header();

?>

<div class="flexdiplay flexdiplaycolumn flexnotgrowcolumn100">

	<div class="menu-bar-flexdisplay">
        <div id="menu-bar-nav">

			<ul id="menu-bar" class="StandartTextLeft">

				<li>
					<a href="internal.php">Kurse</a>
				</li>

				<li>
					<a href="KursVerwaltung.php">Kurse verwalten</a>
				</li>



				<?php if (Bo::isAdmin()) {
					echo '<li><a href="Verwaltung.php">Lehrperson: Verwaltung</a></li>';
				} ?>

				<li>
					<a href="Manuals.php">|&nbsp;Hilfe&nbsp;|</a>
				</li>

			</ul>
		</nav>
	</div>


	<script>
        // async script loading requires to wait for script to be loaded
        egw_LAB.wait(function() {
            jQuery("#menu-bar li").on({
                mouseover: function () {
                    jQuery("#menu-bar li a").removeClass('active')
                },
                mouseout: function () {
                    jQuery("li a[href='" + window.location.pathname.replace('/livefeedbackPLUS/', '') + "']").addClass('active')
                }
            });
            jQuery("li a[href='" + window.location.pathname.replace('/livefeedbackPLUS/', '') + "']").addClass('active')
        });
	</script>
</div>


<div id="VideoList"></div>
<div class="Mid flexgrow flexdiplay">