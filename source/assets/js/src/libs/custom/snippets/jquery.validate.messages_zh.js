/*
 * Translated default messages for the jQuery validation plugin.
 * Locale: ZH (Chinese; 中文 (Zhōngwén), 汉语, 漢語)
 * Region: TW (Taiwan)
 */
(function ($) {
  $.extend($.validator.messages, {
    required: "必填",
    remote: "請修正此欄位",
    email: "請輸入正確的電子信箱",
    url: "請輸入合法的URL",
    date: "請輸入合法的日期",
    dateISO: "請輸入合法的日期 (ISO).",
    number: "請輸入數字",
    digits: "請輸入整數",
    creditcard: "請輸入合法的信用卡號碼",
    equalTo: "重複輸入的值不一樣",
    maxlength: $.validator.format("請輸入長度不大於{0} 的字串"),
    minlength: $.validator.format("請輸入長度不小於 {0} 的字串"),
    rangelength: $.validator.format("請輸入長度介於 {0} 和 {1} 之間的字串"),
    range: $.validator.format("請輸入介於 {0} 和 {1} 之間的數值"),
    max: $.validator.format("請輸入不大於 {0} 的數值"),
    min: $.validator.format("請輸入不小於 {0} 的數值"),

    // additional methos.
    maxWords: "最多輸入 {0} 個字",
    minWords: "至少輸入 {0} 個字",
    rangeWords: "請輸入 {0} 至 {1} 個字",
    letterswithbasicpunc: "只能輸入英文字母或半型標點符號",
    alphanumeric: "只能輸入英文字母、數字或底線",
    lettersonly: "只能輸入英文字母",
    nowhitespace: "必須包含空白",
    zipcodeTW: "請輸入有效的郵遞區號",
    integer: "請輸入正整數或負整數",
    time: "請輸入介於 00:00 與 23:59 的有效時間",
    time12h: "請輸入介於 00:00 am 與 23:59 pm 的有效時間",
    phoneTW: "請輸入有效的電話號碼",
    mobileTW: '請輸入有效的行動電話號碼',
    phoneMobileTW: "請輸入有效的市內電話或行動電話號碼",
    taxNumberTW: '請輸入有效的統一編號',
    idNumberTW: '請輸入有效的身分證編號',
    passportNumberTW: '請輸入有效的護照編號',
    arcNumberTW: '請輸入有效的居留證編號',
    pattern: "格式錯誤",
    require_from_group: "至少填寫 {0} 個欄位",
    skip_or_fill_minimum: "不填寫或至少填寫 {0} 個欄位",
    accept: "請上傳有效的檔案類型",
    extension: "請上傳有效的副檔名",
    checkboxRequired: "至少要勾選 {0} 個選項"
  });
}(jQuery));
