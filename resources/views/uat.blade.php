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
            <div style='font-size: 16px; margin-top: -10px'>Project:&emsp; <b style='font-size: 16px; color: rgb(0, 0, 155);'>{{$projectName}}</b></div>
            <div style='font-size: 16px; margin-top: -10px'>Step: &emsp;&emsp;{{$step}}/3</div>
        </div>

        <br>
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'>Congratulations! We have completed the development of your software. You can now begin <b>UAT</b> esting it at <a href="{{$link}}">link.</a> Let's get started!</div>
        </div>

        <br>
        <div style='padding-bottom: 5px;'>
            <div style='font-size: 16px;'>If you have any more questions, contact <b>PHAT</b> using the provided information.</div>
        </div>

        <br>
        <div style='padding-bottom: 10px;'>
            <div style='font-size: 16px;'><b>Hope you enjoy!</b></div>
            <div style='font-size: 14px; margin-top: -10px'>Project Manager | Mr. Nathaphart Bangkerd</div>
            <div style='font-size: 14px; margin-top: -10px'>Email: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>nathaphart@sncformer.com</u> | Tel: 09 0885 9264</div>
        </div>

        <br>
        <div style='padding-bottom: 3px;'>
            <div><i> <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0); margin-top:-10px'>SNC</b> | <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0);'>99IS</b> | <b style='font-size: 20px; font-family: LilyUPC, sans-serif; color: rgb(255, 0, 0);'>CoDE</b> </i></div>
            <div style='font-size: 14px; margin-top: -10px'>Email: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>code@sncformer.com</u>  | Website: <u style='font-size: 14px; color: rgb(70, 120, 134) !important;'>https://www.snc-code.com</u></div>
        </div>
        <div>
            <div style='font-size: 14px; color: rgb(127, 127, 127) !important;'>This email was generated automatically by iCoDE©. Please refrain from replying.</div>
        </div>
    </body>
</html>

