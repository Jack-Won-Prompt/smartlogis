// Remaining Source (checked)
// Range: custom.js lines 1-1066
// Note: syntax checked after split

"use strict";

const delIcon = `<span class="dash-micon" data-clipboard-text="Deleted" title="" data-bs-original-title="Deleted"
                        data-bs-toggle="tooltip" data-filter="activity" aria-label="activity">
                    <i class="ti ti-recycle"></i>
                    <input type="hidden" name="crud_type" value="D">
                 </span>`;
const udtIcon = `<span class="dash-micon" data-clipboard-text="Updated" title="" data-bs-original-title="Updated"
                       data-bs-toggle="tooltip" data-filter="activity" aria-label="activity">
                    <i class="ti ti-pencil"></i>
                    <input type="hidden" name="crud_type" value="U">
                 </span>`;
const crtIcon = `<span class="dash-micon" data-clipboard-text="Added" title="" data-bs-original-title="Added"
                       data-bs-toggle="tooltip" data-filter="activity" aria-label="activity">
                    <i class="ti ti-plus"></i>
                    <input type="hidden" name="crud_type" value="C">
                 </span>`;

let popupTargetGrid;
let popupTargetInputSetting;
let popupTargetCallback;
let nowOpenPopupModal;


// 커스텀 그리드 캐시 삭제
$(document).on('click', ".page-refresh-btn", function (e) {
    e.stopImmediatePropagation();
    delete_custom_grid();
});

$(document).on("click", '.bs-pass-para',function () {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: 'btn btn-success',
            cancelButton: 'btn btn-danger'
        },
        buttonsStyling: false
    })
    swalWithBootstrapButtons.fire({
        title: $(this).data('confirm'),
        text: $(this).data('text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        reverseButtons: false,
    }).then((result) => {
        if (result.isConfirmed) {
            if($(this).attr('data-id') || $(this).attr('data-id') != undefined || $(this).attr('data-id') != null){
                var id = $(this).attr('data-id');
                if($(this).attr('data-title') == 'language'){
                    $("."+id).submit();
                }else{
                    $("#delete-form-"+id).submit();
                }
            }else{
                document.getElementById($(this).data('confirm-yes')).submit();
            }

        } else if (
            result.dismiss === Swal.DismissReason.cancel
        ) {
        }
    })
});

// $(document).ready(function () {
//     // Custom popup 바깥을 클릭하면 hide() 처리
//     $(document).on('click', function (e) {
//         const popup = $('.custom-popup'); // custom-popup 클래스 가진 요소
//         const popup_content = $('.popup-div'); // custom-popup 내용
//         const target = $(e.target); // 클릭한 대상
//
//         // custom-popup이 보이는 상태에서 영역 밖 클릭 시 hide()
//         if (popup.css('display') !== 'none' && !popup.is(target) && popup.has(target).length === 0) {
//
//             popup.hide();
//             popup_content.html('');
//         }
//     });
// });

$(document).ready(function() {
    $('#commonModal').on('hidden.bs.modal', function () {
        $(this).find('.modal-body').html(''); // 모달 내용 초기화
        autoset_input_code ='';
    });
});

$(".index_clear").on("click", function () {
    window.MULTI_SEARCH_PARAM = {};
});

$(document).ready(function() {
    $('#commonModal2').on('hidden.bs.modal', function () {
        $(this).find('.modal-body').html(''); // 모달 내용 초기화
        autoset_input_code ='';
    });
});

document.addEventListener("DOMContentLoaded", function() {
    document.addEventListener("keydown", function(event) {
        if (event.key === "Enter") {
            // 이벤트의 target이 filter_wrap 내부에 있는지 확인
            const filterWrap = event.target.closest(".filter_wrap");

            if (filterWrap && filterWrap.contains(event.target)) {
                event.preventDefault(); // 기본 Enter 이벤트 동작 방지

                // 해당 filter_wrap 내의 버튼 클릭
                const indexSearchBtn = filterWrap.querySelector(".index_search");
                const searchPopBtn = filterWrap.querySelector("#search_pop_btn");

                if (indexSearchBtn) indexSearchBtn.click();
                if (searchPopBtn) searchPopBtn.click();
            }
        }
    });
});

function syncFont(input, view) {
    const cs = window.getComputedStyle(input);

    const fontProps = [
        'fontFamily',
        'fontSize',
        'fontWeight',
        'fontStyle',
        'letterSpacing',
        'lineHeight',
        'fontVariantNumeric',
        'textTransform'
    ];

    fontProps.forEach(prop => {
        view.style[prop] = cs[prop];
    });
}

function initCommaOverlay(root = document) {
    root.querySelectorAll('.comma-overlay').forEach(input => {
        if (input.dataset.commaInit) return;
        input.dataset.commaInit = 'Y';

        const wrapper = document.createElement('div');
        wrapper.className = 'comma-overlay-wrapper';

        const view = document.createElement('div');
        view.className = 'comma-overlay-view';

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        wrapper.appendChild(view);

        // ✅ 폰트 1차 동기화 (핵심)
        syncFont(input, view);

        const sync = () => {

            const raw = input.value.replace(/[^0-9.-]/g, '');
            view.textContent = raw
                ? Number(raw).toLocaleString('en-US')
                : '';

            // ✅ 값 바뀔 때 폰트도 다시 동기화 (안전)
            syncFont(input, view);
        };

        input.addEventListener('input', sync);
        sync();
    });
}

// ✅ 최초 즉시 실행
initCommaOverlay();

// ✅ 이후 DOM 변경 자동 감지
new MutationObserver(() => {
    initCommaOverlay();
}).observe(document.body, {
    childList: true,
    subtree: true
});

function show_toastr(title, message, type) {
    var o, i;
    var icon = '';
    var cls = '';

    if (type == 'success') {
        icon = 'fas fa-check-circle';
        cls = 'success';
    } else {
        icon = 'fas fa-times-circle';
        cls = 'danger';
    }

    $.notify({icon: icon, title: " " + title, message: message, url: ""}, {
        element: "body",
        type: cls,
        allow_dismiss: !0,
        placement: {
            from: 'top',
            align: toster_pos
        },
        offset: {x: 15, y: 15},
        spacing: 10,
        z_index: 1080,
        delay: 2500,
        timer: 2000,
        url_target: "_blank",
        mouse_over: !1,
        animate: {enter: o, exit: i},
        template: '<div class="alert alert-{0} alert-icon alert-group alert-notify" data-notify="container" role="alert"><div class="alert-group-prepend alert-content"><span class="alert-group-icon"><i data-notify="icon"></i></span></div><div class="alert-content"><strong data-notify="title">{1}</strong><div data-notify="message">{2}</div></div><button type="button" class="close" data-notify="dismiss" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>'
    });
}

$(document).on('click', 'a[data-ajax-popup="true"], button[data-ajax-popup="true"], div[data-ajax-popup="true"]', function () {

    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');
    $("#commonModal .modal-title").html(title);
    $("#commonModal .modal-dialog").addClass('modal-' + size);
    $.ajax({
        url: url,
        success: function (data) {
            $('#commonModal .modal-body').html(data);
            $("#commonModal").modal('show');
            taskCheckbox();
            commonLoader();
        },
        error: function (data) {
            data = data.responseJSON;
            show_toastr('Error', data.error, 'error')
        }
    });

});


$(document).on('click', 'a[data-ajax-popup2="true"], button[data-ajax-popup2="true"], div[data-ajax-popup2="true"]', function () {

    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');
    $("#commonModal2 .modal-title").html(title);
    $("#commonModal2 .modal-dialog").addClass('modal-' + size);
    $.ajax({
        url: url,
        success: function (data) {
            $('#commonModal2 .modal-body').html(data);
            $("#commonModal2").modal('show');
            taskCheckbox();
            commonLoader();
        },
        error: function (data) {
            data = data.responseJSON;
            show_toastr('Error', data.error, 'error')
        }
    });

});



$(document).on('click', 'a[data-ajax-popup-over="true"], button[data-ajax-popup-over="true"], div[data-ajax-popup-over="true"]', function () {

    var validate = $(this).attr('data-validate');
    var id = '';
    if (validate) {
        id = $(validate).val();
    }

    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');

    $("#commonModalOver .modal-title").html(title);
    $("#commonModalOver .modal-dialog").addClass('modal-' + size);

    $.ajax({
        url: url + '?id=' + id,
        success: function (data) {
            $('#commonModalOver .modal-body').html(data);
            $("#commonModalOver").modal('show');
            taskCheckbox();
        },
        error: function (data) {
            data = data.responseJSON;
            show_toastr('Error', data.error, 'error')
        }
    });

});

function arrayToJson(form) {
    var data = $(form).serializeArray();
    var indexed_array = {};

    $.map(data, function (n, i) {
        indexed_array[n['name']] = n['value'];
    });

    return indexed_array;
}

$(document).on("submit", "#commonModalOver form", function (e) {
    e.preventDefault();
    var data = arrayToJson($(this));
    data.ajax = true;

    var url = $(this).attr('action');
    $.ajax({
        url: url,
        data: data,
        type: 'POST',
        success: function (data) {
            show_toastr('Success', data.success, 'success');
            $(data.target).append('<option value="' + data.record.id + '">' + data.record.name + '</option>');
            $(data.target).val(data.record.id);
            $(data.target).trigger('change');
            $("#commonModalOver").modal('hide');
            commonLoader();
        },
        error: function (data) {
            data = data.responseJSON;
            show_toastr('Error', data.error, 'error')
        }
    });
});

// 모달 사이즈 초기화
$(document).on('hidden.bs.modal', '.modal', function () {
    $(this).find('.modal-dialog').removeClass('modal-fullscreen modal-xl modal-lg modal-md')
})

function taskCheckbox() {
    var checked = 0;
    var count = 0;
    var percentage = 0;

    count = $("#check-list input[type=checkbox]").length;
    checked = $("#check-list input[type=checkbox]:checked").length;
    percentage = parseInt(((checked / count) * 100), 10);
    if (isNaN(percentage)) {
        percentage = 0;
    }
    $(".custom-label").text(percentage + "%");
    $('#taskProgress').css('width', percentage + '%');


    $('#taskProgress').removeClass('bg-warning');
    $('#taskProgress').removeClass('bg-primary');
    $('#taskProgress').removeClass('bg-success');
    $('#taskProgress').removeClass('bg-danger');

    if (percentage <= 15) {
        $('#taskProgress').addClass('bg-danger');
    } else if (percentage > 15 && percentage <= 33) {
        $('#taskProgress').addClass('bg-warning');
    } else if (percentage > 33 && percentage <= 70) {
        $('#taskProgress').addClass('bg-primary');
    } else {
        $('#taskProgress').addClass('bg-success');
    }
}

var Charts = (function () {

    // Variable

    var $toggle = $('[data-toggle="chart"]');
    var mode = 'light';//(themeMode) ? themeMode : 'light';
    var fonts = {
        base: 'Open Sans'
    }

    // Colors
    var colors = {
        gray: {
            100: '#f6f9fc',
            200: '#e9ecef',
            300: '#dee2e6',
            400: '#ced4da',
            500: '#adb5bd',
            600: '#8898aa',
            700: '#525f7f',
            800: '#32325d',
            900: '#212529'
        },
        theme: {
            'default': '#172b4d',
            'primary': '#5e72e4',
            'secondary': '#f4f5f7',
            'info': '#11cdef',
            'success': '#2dce89',
            'danger': '#f5365c',
            'warning': '#fb6340'
        },
        black: '#12263F',
        white: '#FFFFFF',
        transparent: 'transparent',
    };


    // Methods

    // Chart.js global options
    function chartOptions() {

        // Options
        var options = {
            defaults: {
                global: {
                    responsive: true,
                    maintainAspectRatio: false,
                    defaultColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontFamily: fonts.base,
                    defaultFontSize: 13,
                    layout: {
                        padding: 0
                    },
                    legend: {
                        display: false,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 16
                        }
                    },
                    elements: {
                        point: {
                            radius: 0,
                            backgroundColor: colors.theme['primary']
                        },
                        line: {
                            tension: .4,
                            borderWidth: 4,
                            borderColor: colors.theme['primary'],
                            backgroundColor: colors.transparent,
                            borderCapStyle: 'rounded'
                        },
                        rectangle: {
                            backgroundColor: colors.theme['warning']
                        },
                        arc: {
                            backgroundColor: colors.theme['primary'],
                            borderColor: (mode == 'dark') ? colors.gray[800] : colors.white,
                            borderWidth: 4
                        }
                    },
                    tooltips: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                    }
                },
                doughnut: {
                    cutoutPercentage: 83,
                    legendCallback: function (chart) {
                        var data = chart.data;
                        var content = '';

                        data.labels.forEach(function (label, index) {
                            var bgColor = data.datasets[0].backgroundColor[index];

                            content += '<span class="chart-legend-item">';
                            content += '<i class="chart-legend-indicator" style="background-color: ' + bgColor + '"></i>';
                            content += label;
                            content += '</span>';
                        });

                        return content;
                    }
                }
            }
        }

        // yAxes
        Chart.scaleService.updateScaleDefaults('linear', {
            gridLines: {
                borderDash: [2],
                borderDashOffset: [2],
                color: (mode == 'dark') ? colors.gray[900] : colors.gray[300],
                drawBorder: false,
                drawTicks: false,
                drawOnChartArea: true,
                zeroLineWidth: 0,
                zeroLineColor: 'rgba(0,0,0,0)',
                zeroLineBorderDash: [2],
                zeroLineBorderDashOffset: [2]
            },
            ticks: {
                beginAtZero: true,
                padding: 10,
                callback: function (value) {
                    if (!(value % 10)) {
                        return value
                    }
                }
            }
        });

        // xAxes
        Chart.scaleService.updateScaleDefaults('category', {
            gridLines: {
                drawBorder: false,
                drawOnChartArea: false,
                drawTicks: false
            },
            ticks: {
                padding: 20
            },
            maxBarThickness: 10
        });

        return options;

    }

    // Parse global options
    function parseOptions(parent, options) {
        for (var item in options) {
            if (typeof options[item] !== 'object') {
                parent[item] = options[item];
            } else {
                parseOptions(parent[item], options[item]);
            }
        }
    }

    // Push options
    function pushOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].push(data);
                });
            } else {
                pushOptions(parent[item], options[item]);
            }
        }
    }

    // Pop options
    function popOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].pop();
                });
            } else {
                popOptions(parent[item], options[item]);
            }
        }
    }

    // Toggle options
    function toggleOptions(elem) {
        var options = elem.data('add');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        if (elem.is(':checked')) {

            // Add options
            pushOptions($chart, options);

            // Update chart
            $chart.update();
        } else {

            // Remove options
            popOptions($chart, options);

            // Update chart
            $chart.update();
        }
    }

    // Update options
    function updateOptions(elem) {
        var options = elem.data('update');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        // Parse options
        parseOptions($chart, options);

        // Toggle ticks
        toggleTicks(elem, $chart);

        // Update chart
        $chart.update();
    }

    // Toggle ticks
    function toggleTicks(elem, $chart) {

        if (elem.data('prefix') !== undefined || elem.data('prefix') !== undefined) {
            var prefix = elem.data('prefix') ? elem.data('prefix') : '';
            var suffix = elem.data('suffix') ? elem.data('suffix') : '';

            // Update ticks
            $chart.options.scales.yAxes[0].ticks.callback = function (value) {
                if (!(value % 10)) {
                    return prefix + value + suffix;
                }
            }

            // Update tooltips
            $chart.options.tooltips.callbacks.label = function (item, data) {
                var label = data.datasets[item.datasetIndex].label || '';
                var yLabel = item.yLabel;
                var content = '';

                if (data.datasets.length > 1) {
                    content += '<span class="popover-body-label mr-auto">' + label + '</span>';
                }

                content += '<span class="popover-body-value">' + prefix + yLabel + suffix + '</span>';
                return content;
            }

        }
    }


    // Events

    // Parse global options
    if (window.Chart) {
        parseOptions(Chart, chartOptions());
    }

    // Toggle options
    $toggle.on({
        'change': function () {
            var $this = $(this);

            if ($this.is('[data-add]')) {
                toggleOptions($this);
            }
        },
        'click': function () {
            var $this = $(this);

            if ($this.is('[data-update]')) {
                updateOptions($this);
            }
        }
    });


    // Return

    return {
        colors: colors,
        fonts: fonts,
        mode: mode
    };
})();

function commonLoader() {
    if ($(".datepicker").length) {
        $('.datepicker').daterangepicker({
            locale: date_picker_locale,
            singleDatePicker: true,
        });
    }

    if ($(".date-rangepicker").length > 0) {
        $('.date-rangepicker').daterangepicker({
            locale: date_picker_locale,
        });
    }

    if ($(".select2").length) {
        $(".select2").select2({
            disableOnMobile: false,
            nativeOnMobile: false
        });
    }

    if ($(".summernote-simple").length) {
        $('.summernote-simple').summernote({
            dialogsInBody: !0,
            minHeight: 200,
            toolbar: [
                ['style', ['style']],
                ["font", ["bold", "italic", "underline", "clear", "strikethrough"]],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ["para", ["ul", "ol", "paragraph"]],
            ]
        });
    }

    if ($(".jscolor").length > 0) {
        jscolor.installByClassName("jscolor");
    }

    // for Choose file
    $(document).on('change', 'input[type=file]', function () {
        var fileclass = $(this).attr('data-filename');
        var finalname = $(this).val().split('\\').pop();
        $('.' + fileclass).html(finalname);
    });
}

(function ($, window, i) {
    // Bootstrap 4 Modal
    $.fn.fireModal = function (options) {
        var options = $.extend({
            size: 'modal-md',
            center: false,
            animation: true,
            title: 'Modal Title',
            closeButton: false,
            header: true,
            bodyClass: '',
            footerClass: '',
            body: '',
            buttons: [],
            autoFocus: true,
            created: function () {
            },
            appended: function () {
            },
            onFormSubmit: function () {
            },
            modal: {}
        }, options);
        this.each(function () {
            i++;
            var id = 'fire-modal-' + i,
                trigger_class = 'trigger--' + id,
                trigger_button = $('.' + trigger_class);
            $(this).addClass(trigger_class);
            // Get modal body
            let body = options.body;
            if (typeof body == 'object') {
                if (body.length) {
                    let part = body;
                    body = body.removeAttr('id').clone().removeClass('modal-part');
                    part.remove();
                } else {
                    body = '<div class="text-danger">Modal part element not found!</div>';
                }
            }
            // Modal base template
            var modal_template = '   <div class="modal' + (options.animation == true ? ' fade' : '') + '" tabindex="-1" role="dialog" id="' + id + '">  ' +
                '     <div class="modal-dialog ' + options.size + (options.center ? ' modal-dialog-centered' : '') + '" role="document">  ' +
                '       <div class="modal-content">  ' +
                ((options.header == true) ?
                    '         <div class="modal-header">  ' +
                    '           <h5 class="modal-title mx-auto">' + options.title + '</h5>  ' +
                    ((options.closeButton == true) ?
                        '           <button type="button" class="close" data-dismiss="modal" aria-label="Close">  ' +
                        '             <span aria-hidden="true">&times;</span>  ' +
                        '           </button>  '
                        : '') +
                    '         </div>  '
                    : '') +
                '         <div class="modal-body text-center text-dark">  ' +
                '         </div>  ' +
                (options.buttons.length > 0 ?
                    '         <div class="modal-footer mx-auto">  ' +
                    '         </div>  '
                    : '') +
                '       </div>  ' +
                '     </div>  ' +
                '  </div>  ';
            // Convert modal to object
            var modal_template = $(modal_template);
            // Start creating buttons from 'buttons' option
            var this_button;
            options.buttons.forEach(function (item) {
                // get option 'id'
                let id = "id" in item ? item.id : '';
                // Button template
                this_button = '<button type="' + ("submit" in item && item.submit == true ? 'submit' : 'button') + '" class="' + item.class + '" id="' + id + '">' + item.text + '</button>';
                // add click event to the button
                this_button = $(this_button).off('click').on("click", function () {
                    // execute function from 'handler' option
                    item.handler.call(this, modal_template);
                });
                // append generated buttons to the modal footer
                $(modal_template).find('.modal-footer').append(this_button);
            });
            // append a given body to the modal
            $(modal_template).find('.modal-body').append(body);
            // add additional body class
            if (options.bodyClass) $(modal_template).find('.modal-body').addClass(options.bodyClass);
            // add footer body class
            if (options.footerClass) $(modal_template).find('.modal-footer').addClass(options.footerClass);
            // execute 'created' callback
            options.created.call(this, modal_template, options);
            // modal form and submit form button
            let modal_form = $(modal_template).find('.modal-body form'),
                form_submit_btn = modal_template.find('button[type=submit]');
            // append generated modal to the body
            $("body").append(modal_template);
            // execute 'appended' callback
            options.appended.call(this, $('#' + id), modal_form, options);
            // if modal contains form elements
            if (modal_form.length) {
                // if `autoFocus` option is true
                if (options.autoFocus) {
                    // when modal is shown
                    $(modal_template).on('shown.bs.modal', function () {
                        // if type of `autoFocus` option is `boolean`
                        if (typeof options.autoFocus == 'boolean')
                            modal_form.find('input:eq(0)').focus(); // the first input element will be focused
                        // if type of `autoFocus` option is `string` and `autoFocus` option is an HTML element
                        else if (typeof options.autoFocus == 'string' && modal_form.find(options.autoFocus).length)
                            modal_form.find(options.autoFocus).focus(); // find elements and focus on that
                    });
                }
                // form object
                let form_object = {
                    startProgress: function () {
                        modal_template.addClass('modal-progress');
                    },
                    stopProgress: function () {
                        modal_template.removeClass('modal-progress');
                    }
                };
                // if form is not contains button element
                if (!modal_form.find('button').length) $(modal_form).append('<button class="d-none" id="' + id + '-submit"></button>');
                // add click event
                form_submit_btn.click(function () {
                    modal_form.submit();
                });
                // add submit event
                modal_form.submit(function (e) {
                    // start form progress
                    form_object.startProgress();
                    // execute `onFormSubmit` callback
                    options.onFormSubmit.call(this, modal_template, e, form_object);
                });
            }
            $(document).on("click", '.' + trigger_class, function () {
                $('#' + id).modal(options.modal);
                return false;
            });
        });
    }
    // Bootstrap Modal Destroyer
    $.destroyModal = function (modal) {
        modal.modal('hide');
        modal.on('hidden.bs.modal', function () {
        });
    }
})(jQuery, this, 0);

$('[data-confirm]').each(function () {
    var me = $(this),
        me_data = me.data('confirm');

    me_data = me_data.split("|");
    me.fireModal({
        title: me_data[0],
        body: me_data[1],
        buttons: [
            {
                text: me.data('confirm-text-yes') || 'Yes',
                class: 'btn btn-sm btn-danger rounded-pill',
                handler: function () {
                    eval(me.data('confirm-yes'));
                }
            },
            {
                text: me.data('confirm-text-cancel') || 'Cancel',
                class: 'btn btn-sm btn-secondary rounded-pill',
                handler: function (modal) {
                    $.destroyModal(modal);
                    eval(me.data('confirm-no'));
                }
            }
        ]
    })
});

function selectFile(elementid){
    $(`.${elementid}`).trigger('click');
    $(`.${elementid}`).change(function() {
        var url = this.value;
        var ext = url.substring(url.lastIndexOf('.') + 1).toLowerCase();
        if (this.files && this.files[0]&& (ext == "gif" || ext == "png" || ext == "jpeg" || ext == "jpg")) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $(`#${elementid}`).attr('src', e.target.result);
                $(`#section_${elementid}`).attr('src', e.target.result);
                $(`#${elementid}_preview`).attr('src', e.target.result);
            }
            reader.readAsDataURL(this.files[0]);
        }else{
            $(`#${elementid}`).attr('src', '/assets/no_preview.png');

            $(`#section_${elementid}`).attr('src', '/assets/no_preview.png');
        }
    });
}

// Form POST Download (binary file download via AJAX blob)
function formPostDownload(url, formData) {
    var data = { _token: $('meta[name="csrf-token"]').attr('content') };
    $.each(formData, function(i, field) {
        data[field.name] = field.value;
    });

    $.ajax({
        type: 'POST',
        url: url,
        data: data,
        xhrFields: { responseType: 'blob' },
        beforeSend: function() {
            $('.loader-wrap').addClass('show');
        },
        success: function(blob, status, xhr) {
            var filename = 'download.xlsx';
            var disposition = xhr.getResponseHeader('Content-Disposition');
            if (disposition) {
                var match = disposition.match(/filename\*?=(?:UTF-8'')?([^;\n"]+)/i);
                if (match) filename = decodeURIComponent(match[1].replace(/['"]/g, ''));
            }
            var blobUrl = window.URL.createObjectURL(blob);
            var $a = $('<a>').attr({ href: blobUrl, download: filename });
            $('body').append($a);
            $a[0].click();
            $a.remove();
            window.URL.revokeObjectURL(blobUrl);
            show_toastr('success', '다운로드가 완료되었습니다.');
        },
        error: function() {
            show_toastr('error', '다운로드에 실패했습니다.');
        },
        complete: function() {
            $('.loader-wrap').removeClass('show');
        }
    });
}

// Post Ajax Call
function postAjax(url, data, is_formData, cb) {
    var token = $('meta[name="csrf-token"]').attr('content');
    if(is_formData){
        var jdata = data;
        $.ajax({
            enctype: 'multipart/form-data',
            type: 'POST',
            url: url,
            data: jdata,
            processData: false,
            contentType: false,
            cache: false,
            success: function (data) {
                if (typeof (data) === 'object') {
                    cb(data);
                } else {
                    cb(data);
                }
            },
            error: function (e) {
                console.log(e);
                alert('error');
            },
            beforeSend: function () {
                $('.loader-wrap').addClass('show');
            },
            complete: function () {
                $('.loader-wrap').removeClass('show');
            }
        });
    }else{
        // var jdata = {_token: token};
        // for (var k in data) {
        //     jdata[k] = data[k];
        // }
        var jdata = data;
        $.ajax({
            type: 'POST',
            url: url,
            data: jdata,
            success: function (data) {
                $('.loader-wrap').removeClass('show');
                if (typeof (data) === 'object') {
                    cb(data);
                } else {
                    cb(data);
                }
            },
            error: function (e) {
                $('.loader-wrap').removeClass('show');
                cb('error');
            },
            beforeSend: function () {
                $('.loader-wrap').addClass('show');
            },
            complete: function () {
                // $('.loader-wrap').removeClass('show');
            }
        });
    }
}

// Get Ajax Call
function getAjax(url, data, cb, loader = true) {
    $.ajax({
        type: "GET",
        url: url,
        data: data,
        success: function (data) {
            if(loader) $('.loader-wrap').removeClass('show');
            cb(data);
        },
        error: function (e) {
            if(loader) $('.loader-wrap').removeClass('show');
            console.log(e);
        },
        beforeSend: function () {
            if(loader) $('.loader-wrap').addClass('show');
        },
        complete: function () {
            // $('.loader-wrap').removeClass('show');
        }
    });
}

//TODO:: ajax 데이터를 변수에 저장하고 return하는 함수 만들어야함
// let ajaxDataField = null;
// function dataSet(param) {
//     ajaxDataField = param;
// };
// function getAjaxData(url, data, cb, loader = true) {
//
//     $.ajax({
//         type: "GET",
//         url: url,
//         data: data,
//         success: function (data) {
//             if(loader) $('.loader-wrap').removeClass('show');
//             dataSet(data)
//         },
//         error: function (e) {
//             if(loader) $('.loader-wrap').removeClass('show');
//             console.log(e);
//         },
//         beforeSend: function () {
//             if(loader) $('.loader-wrap').addClass('show');
//         },
//         complete: function () {
//             // $('.loader-wrap').removeClass('show');
//         }
//     });
//
//     console.log('ajaxDataField',ajaxDataField)
//     return ajaxDataField;
// }

function dblclk(id){ // 미사용 함수
    $('.nav-link').attr('disabled', false);
    $('.nav-link').not('#pills-profile-tab').removeClass("active");
    $('.tab-pane').not('#pills-profile').removeClass("show active");
    $(".nav-link#pills-profile-tab").addClass("active");
    $(".tab-pane#pills-profile").addClass("show active");
    getNewDetails('edit',id);
}

function itemdblclk(id){ // 미사용 함수
    getNewDetails('edit',id);
}

$(document).on('click', '#checkAll', function (e) {
    let table = $(this).parents('.dataTables_scroll');
    table = table.length < 1 ? $(this).parents('table') : table;
    table.find('input:checkbox:not([disabled])').prop('checked', this.checked);
});

$(document).on('click', '#checkAll2', function (e) {
    let table = $(this).parents('.dataTables_scroll');
    table = table.length < 1 ? $(this).parents('table') : table;
    table.find('.change-check').prop('checked', this.checked);
});

// function resetInput(name) {
//     $('input[name=' + name + '_code]').keyup(function(e) {
//         $('input[name=' + name + '_id]').val('');
//         $('input[name=' + name + '_name]').val('');
//         $('#' + name + '_code_hidden').val('');
//         if (name == 'address') {
//             $('input[name=' + name + '_line_1]').val('');
//             $('input[name=' + name + '_line_2]').val('');
//         }
//     });
//     $('input[name=' + name + '_name]').keyup(function(e) {
//         $('input[name=' + name + '_id]').val('');
//         $('input[name=' + name + '_code]').val('');
//         $('#' + name + '_code_hidden]').val('');
//     });
// }

// 공통 검색 팝업 input 자동 세팅 및 수동 입력 막기 기능
var autoset_input_code = ''; //input 세팅값 공통 선언
