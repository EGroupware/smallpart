</div>
<div class="footer">
	<!--	<footer>-->
	<div id="footerLeft">
		<a href="http://www.fdbio-tukl.de/" target="_blank" class="navbar-brand"><img
				src="Resources/Gifs/fdlogo.png"
				style="height:30px; margin-left:30px; Background-color:#ffffff;"></a>
		<a href="https://www.uni-kl.de/" target="_blank" class="navbar-brand"><img
				src="Resources/Gifs/TUKL_LOGO_SCHRIFTZUG_transparent_RGB.png"
				style="float:right; height:25px; margin-left:30px;"></a>
		<a href="https://uni-tuebingen.de/" target="_blank" class="navbar-brand"><img
				src="Resources/Gifs/UT_WBMW_Rot_RGB.jpg"
				style="float:right; height:25px; margin-left:30px;"></a>
	</div>
	<div id="footerRight" style="padding-right: 60px; line-height: 50px; float: bottom">

		&nbsp;
		<a href="Manuals.php" target="_blank">Anleitung</a>
		&nbsp;
		&nbsp;
		<a href="https://www.uni-kl.de/impressum/" target="_blank">Impressum</a>
		&nbsp;
		&nbsp;
		<a href="http://www.fdbio-tukl.de/index.php?id=1017" target="_blank">Kontakt</a>


	</div>
	<!--	</footer>-->
</div>
</di>

<!-- Bootstrap core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="../../assets/js/vendor/jquery.min.js"><\/script>')</script>
<script src="js/bootstrap.min.js"></script>

<script>

    function FunkZoomSite() {

        if ($(window).width() < 767) {
            $('#LoginWidthToSmall').remove()
            $('#menu-bar-Logo').after('<span id="LoginWidthToSmall"><a href="login.php">einloggen</a></span>')
        } else {
            $('#LoginWidthToSmall').remove()

        }
        if ($(window).width() >= 1450) {
            $('html, #menu-bar-Logo, #menu-bar-nav').css('zoom', '1')
        }
        if ($(window).width() < 1450) {
            var MinScreenWidth = 1450
            var MinZoomLvlHtml = .85;
            var MinZoomLvlLogo = .30;
            var MinZoomLvlNav = .75;
            var ActiveZoomLvl = 0;
            var ZoomLvl = $(window).width() / MinScreenWidth
            ZoomLvl = ZoomLvl.toFixed(2)

            if (ZoomLvl > MinZoomLvlHtml) {
                $('html, #menu-bar-Logo, #menu-bar-nav').css('zoom', ZoomLvl)
                ActiveZoomLvl = Math.floor((1 - ZoomLvl) * 100)

                $('.smalldisplay').show().html('Die Seite ist für virtuelle Bildschirmauflösungen mit mind. <u>1450 Pixeln</u> optimiert. Bei kleineren Einstellungen ist der Kommentarbereich nicht neben dem Video sichtbar. Sie haben <u>' +
                    $(window).width() +
                    ' Pixeln</u>. Die Darstellung des Inhaltes wurde dem entsprechend automatisch um maximal <u>' +
                    ActiveZoomLvl +
                    '%</u> verkleinert'
                )

            } else {
                $('html').css('zoom', MinZoomLvlHtml)
                $('#menu-bar-Logo').css('zoom', MinZoomLvlLogo)
                $('#menu-bar-nav').css('zoom', MinZoomLvlNav)

                ActiveZoomLvl = Math.floor((1 - MinZoomLvlHtml) * 100)


                $('.smalldisplay').show().html('Die Seite ist für virtuelle Bildschirmauflösungen mit mind. <u>1450 Pixeln</u> optimiert. Bei kleineren Einstellungen ist der Kommentarbereich nicht neben dem Video sichtbar. Sie haben <u>' +
                    $(window).width() +
                    ' Pixeln</u>. Die Darstellung des Inhaltes wurde dem entsprechend automatisch um maximal <u>' +
                    ActiveZoomLvl +
                    '%</u> verkleinert' +
                    'Sie können die Zoomstufe Ihres Browsers über das Menue des Browsers mit der Tastenkombination "strg"&"-" oder "strg"&"+" manuell anpassen, um die Seite besser zu bearbeiten.'
                )
            }

            //.delay(10000).fadeOut('slow')
        } else {
            $('.smalldisplay').hide()
        }

    }


    // $(document).on('load', function () {
    console.log('bin da')
    FunkZoomSite()
    // FunkZoomBar()
    // FunkZoomLogo()
    // })

    // if($( window ).width()<1450){
    $(window).on('resize', function () {
        FunkZoomSite()
        // FunkZoomBar()
        // FunkZoomLogo()
    })

</script>
</body>
</html>