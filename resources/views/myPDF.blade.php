<html>
    <head>
        <title>Image</title>
    </head>
    <body>
        <div class="">
            <?php
            /*
            // $img_file = public_path('img/documents/'.$image);
            $img_file = $image;
            $imgData = base64_encode(file_get_contents($img_file));

            // Format the image SRC:  data:{mime};base64,{data};
            $src = 'data:image/png;base64,'.$imgData;*/

            //echo $imageUrl; die;
            ?>
            <!--<img style="width:100%;" src="{{--$src--}}">-->

            <img style="width:100%;" src="{{$imageUrl}}">

        </div>
    </body>
</html>
