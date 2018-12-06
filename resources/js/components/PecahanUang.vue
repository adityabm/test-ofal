<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-default">
                    <div class="card-body">
                        Masukkan Jumlah Uang : <input type="number" v-model="uang" class="form-control">
                        <button type="button" class="btn btn-success btn-sm mt-2" @click="submit(uang)">Submit</button>
                    </div>
                    <div id="output" class="px-3 pb-3">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data(){
            uang:0
        },
        methods:{
            submit(uang){
                var tmpuang = uang;
                var pecahan = [100000,50000,20000,10000,5000,2000,1000,500,200,100];
                var string = '';

                for(var a = 0; a < pecahan.length;a++){
                    if(tmpuang >= pecahan[a]){
                        var jum = parseInt(tmpuang / pecahan[a]);
                        string += jum + 'x ' + pecahan[a] + '<br>';
                        tmpuang = tmpuang - (jum * pecahan[a]);
                    }
                }

                $('#output').html(string);
            }
        },
        mounted() {
            console.log('Component mounted.')
        }
    }
</script>
