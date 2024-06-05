<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">

            <title>Laravel</title>

            <!-- Fonts -->
            <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

            <style>
                body {
                    font-family: 'Nunito', sans-serif;
                }
            </style>
    </head>
    <body class="antialiased">
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'>Hi &emsp;&emsp;&emsp; <b style='font-size: 16px; color: rgb(0, 0, 155);'>{{$customerName}}</b>,</div>
        </div>

        <br>
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'>Thanks for validation the document! 🎉 We've got it and we're ready to move forward.</div>
        </div>

        <br>
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'> If there is anything else you need, just give us a shout! </div>
            <div style='font-size: 16px;'> For more information, <a href="{{$link}}">click here.</a></div>
        </div>

        <br>
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'> Best,</div>
        </div>

        <br>
        <div style='padding-bottom: 10px;'>
            <div style='font-size: 14px;'>Project Manager | Mr. Nathaphart Bangkerd</div>
            <div style='font-size: 14px;'>Email: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>nathaphart@sncformer.com</u> | Tel: 09 0885 9264</div>
        </div>

        <br>
        <div style='padding-bottom: 3px;'>
            <div ><i> <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0); margin-top:-10px'>SNC</b> | <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0);'>99IS</b> | <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0);'>CoDE</b> </i></div>
            <div style='font-size: 14px;'>Email: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>code@sncformer.com</u>  | Website: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>https://www.snc-code.com</u></div>
        </div>
        <div>
            <div style='font-size: 14px; color: rgb(127, 127, 127) !important;'>This email was generated automatically by iCoDE©. Please refrain from replying.</div>
        </div>

    </body>
</html>
