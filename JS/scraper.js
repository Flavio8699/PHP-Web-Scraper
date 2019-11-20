var i = 0,
    nextID = 0,
    nextLevel = 1,
    firstCall = true,
    type = '';

function startScraper() {
    condition = createCondition();

    if (condition === '') {
        alert('Please create a condition!');
        return;
    }

    $("input, select").each(function() {
        $(this).attr("disabled", "true");
    });

    $("#startScraperButton, #addConditionButton").attr("disabled", "true");

    var formData = new FormData();
    if (firstCall) {
        formData.append('firstCall', firstCall)
        formData.append('csv', $('#csv')[0].files[0]);
        formData.append('url', $("#url").val());
    }
    formData.append('condition', condition);
    formData.append('levels', $("#levels").val());
    formData.append('urlID', nextID);
    formData.append('level', nextLevel);

    $.ajax({
        url: 'PHP/scraper.php',
        data: formData,
        type: 'POST',
        cache: false,
        contentType: false,
        mimeType: 'multipart/form-data',
        processData: false,
        success: function(e) {
            e = JSON.parse(e);
            firstCall = false;

            writeLineToConsole("URL: " + e.url + "\n &rarr; MATCHES: " + e.matches);
            if (e.continue === true) {
                nextID = e.nextURL.id;
                nextLevel = e.nextURL.level;
                startScraper();
            } else {
                if (type == 'url') {
                    $("#calculations").slideDown();
                    $.get('PHP/scraper.php?getResults', function(r) {
                        $("#result_1").html(r[0]);
                        $("#result_2").html(r[1]);
                        $("#result_3").html(r[2]);
                        $("#result_4").html(r[3]);
                        $("#result_5").html(r[4]);
                    });
                }
                writeLineToConsole("-- END --");
                $("#export").prop("disabled", false);
            }

            if ($("textarea").length)
                $("textarea").scrollTop($("textarea")[0].scrollHeight - $("textarea").height());
        }
    });
}

$(function() {
    $("#export").on('click', function() {
        window.open('result.csv');
    });
});

function writeLineToConsole(msg) {
    $("#console").append(msg + "\n\n");
}

function addCondition() {
    i++;
    $("#conditions").append('<input type="text" class="form-control user_condition" placeholder="Condition"><br>');
    $("#connectors").append('<select class="form-control" id="connector_' + i + '"><option value="AND">AND</option><option value="OR">OR</option><option value="ANDNOT">ANDNOT</option><option value="ORNOT">ORNOT</option></select><br>');
}

function choice(c) {
    $("#urlChoice").slideUp();
    $("#" + c).slideDown();
    $("#rest").slideDown();

    if (c == 'insertURL') {
        type = 'url';
    } else {
        type = 'file';
    }
}

function createCondition() {
    var conditions_array = [],
        condition = "",
        conditions = document.getElementsByClassName("user_condition");

    for (var i = 0; i < conditions.length; ++i) {
        var item = conditions[i],
            connector = document.getElementById("connector_" + i);

        if (item.value)
            conditions_array.push({
                condition: item.value.replace(" ", "-"),
                connector: connector.value
            });
    }

    conditions_array.forEach(function(element, index) {
        if (index === 0)
            condition = element.condition;
        else
            condition += "+" + element.connector + "+" + element.condition;
    });

    return condition;
}