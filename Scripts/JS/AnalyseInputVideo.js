//Need KorrekturSchabloneArray + InputMarkedArea + Time
// Get data from GitterNetz.js;
// Check auf x% Abweichung der Eingabe zur Schablone
function FunkCheckMarkedArea(InputMarkedArea) {
    let Bestanden;
    // todo Testvar aus DB importieren
    //change richtiger Länge Testvar
    //const Testvar = '111111100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';
    const Testvar =["0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","1","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","1","1","1","1","1","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0","0"]

    //change Falscher länge
    //const Testvar ='0111111100000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';
    //alert (Testvar.length);
    //  alert(InputMarkedArea);
    //  alert(KorrekturSchabloneArray);

    //AjaxSend('database/DbInteraktion.php', {DbRequest: "Select", DbRequestVariation:"KorrekturSchablone", AjaxDataToSend: {UserID: "0"}}, 'FunkLoadVideo');

    //------------------------------
    //fixme: input aus DB statt
    //let KorrekturSchabloneArray = Testvar.split('');
    let KorrekturSchabloneArray = Testvar;
    //let KorrekturSchabloneArray = AjaxGet.KorrekturSchablone.split('');
    //alert (KorrekturSchabloneArray);
    let Differenz = 0;
    let CountKorrekturschablone = 0;
    let CountMarkedArea = 0;
    let CheckEquality;
    for (let i = 0; i < KorrekturSchabloneArray.length; i++) {
        if (KorrekturSchabloneArray.length !== InputMarkedArea.length) {
            CheckEquality = false;
        } else {
            CheckEquality = true;
            CountKorrekturschablone += parseInt(KorrekturSchabloneArray[i]);
            CountMarkedArea += parseInt(InputMarkedArea[i]);
            if (InputMarkedArea[i] !== KorrekturSchabloneArray[i]) {
                Differenz++;
            }
        }
    }
    if (CheckEquality) {
        let Fehlerquote = Math.floor(Differenz / CountKorrekturschablone * 100);
        //todo variable
        const Toleranz = 77;
        if (Fehlerquote <= Toleranz) {
            Bestanden = "Ja";
        } else {
            Bestanden = "Nein";
        }
        //------------------------------
        // jQuery("#VideoDivTop").html(
        //     "Bestanden:  <b>" + Bestanden + "</b> | Tollerierte Abweichung: " + Toleranz + "%" +
        //     "<br> Anzahl markierte Kacheln" +
        //     "<br> | Deine Markierungsanzahl: " + CountMarkedArea +
        //     " &nbsp&nbsp|&nbsp&nbsp CountKorrekturschablone:" + CountKorrekturschablone +
        //     " &nbsp&nbsp|&nbsp&nbsp Diferenz: " + Differenz +
        //     " &nbsp&nbsp|&nbsp&nbsp Fehlerquote:  " + Differenz + "/" + CountKorrekturschablone + "=" + Fehlerquote + "%"
        // );
        //
        // jQuery("#CommentsCheckArea22").html(
        //     "Bestanden:  <b>" + Bestanden + "</b> | Tollerierte Abweichung: " + Toleranz + "%" +
        //     "<br> Anzahl markierte Kacheln" +
        //     "<br> | Deine Markierungsanzahl: " + CountMarkedArea +
        //     " &nbsp&nbsp|&nbsp&nbsp CountKorrekturschablone:" + CountKorrekturschablone +
        //     " &nbsp&nbsp|&nbsp&nbsp Diferenz: " + Differenz +
        //     " &nbsp&nbsp|&nbsp&nbsp Fehlerquote:  " + Differenz + "/" + CountKorrekturschablone + "=" + Fehlerquote + "%"
        // );

        alert("Bestanden: ## "+Bestanden +" ## (Tollerierte Abweichung: " + Toleranz + "%) \n"+
        "Anzahl markierte Kacheln \n"+
        "Deine Markierungsanzahl: " + CountMarkedArea + "\nCountKorrekturschablone:" + CountKorrekturschablone +"\nDiferenz: " + Differenz +"\nFehlerquote:  " + Differenz + "/" + CountKorrekturschablone + "=" + Fehlerquote + "%"


        );
    } else {
        jQuery("#VideoDivTop").html("Fehler: Eingabenmaske und Schablone passen nicht zueinander")
    }
}

//------------------------------

//Need KorrekturSchabloneArray + InputMarkedArea + Time
// Get data from GitterNetz.js;
// Check ob Eingabe in Schablone liegt
// TODO
/*  function FunkCheckMarkedPosition(InputMarkedArea) {


}*/


//Need KorrekturSchabloneArray + InputMarkedArea + Time
// Get data from GitterNetz.js;
// Check ob Eingabe in Schablone (eingabe in fließender Fehlerperiode) liegt
// TODO
/*  function FunkCheckMarkedFlowArea(InputMarkedArea) {


}*/


//clean
/* //Korrekturschablone +=parseInt(Korrekturarray[i]);
 //CountMarkedArea+= parseInt(InputMarkedArea[i]);


 //----------------------
 // /*if ( (!array1[0]) || (!array2[0]) ) { // If either is not an array
 //     return false;
 // }/
 // if (darray1.length != darray2.length) {
 //     return false;
 // }
 // for (var i=0; i<darray1.length; i++) {
 //     if (darray1[i]== darray2[i]){
 //
 //     }else {
 //         Differenz++;
 //     }
 // };
 /// Put all the elements from array1 into a "tagged" array
 for (var i=0; i<array1.length; i++) {
     key = (typeof array1[i]) + "~" + array1[i];
     // Use "typeof" so a number 1 isn't equal to a string "1".
     if (temp[key]) { temp[key]++; } else { temp[key] = 1; }
     // temp[key] = # of occurrences of the value (so an element could appear multiple times)
 }
 // Go through array2 - if same tag missing in "tagged" array, not equal
 for (var i=0; i<array2.length; i++) {
     key = (typeof array2[i]) + "~" + array2[i];
     if (temp[key]) {
         if (temp[key] == 0) { return false; } else { temp[key]--; }
         // Subtract to keep track of # of appearances in array2
     } else { // Key didn't appear in array1, arrays are not equal.
         return false;
     }
 }
 // If we get to this point, then every generated key in array1 showed up the exact same
 // number of times in array2, so the arrays are equal.

 //return true;
}
*/