<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function review($id = null)
    {
        // Fungsi ini berguna untuk mengacak pertanyaan untuk mereview setiap pegawai,
        // Disini menggunaka Twister dimana pertanyaan akan sama walaupun di refresh halamannya dan pada setiap bulannya akan berubah pertanyaannya sesuai acakan ada twisternya.
        
        $nip = getNip();
        $data['nip_reviewer'] = getNip();
        $pilih = array();
        $bulan = 0;
        $p = Pegawai::where('peg_id',$id)->first();
        if(!$p){
            $cari = DB::connection('simpeg')->table('spg_pegawai')->selectRaw('gol_id_akhir as gol_id,*')->where('peg_id',$id)->first();
            $p = Pegawai::initFromView($cari);
        }
        if($p){
            if($p->peg_status == null){
                $pensiun = DB::connection('simpeg')->table('spg_pensiun')->where('peg_id',$p->peg_id)->first();
                $data['nama_pegawai'] = $p->peg_nama.' (Pensiun : '.getFullDate($pensiun->rpensiun_tglsk).')';
            }else{
                $data['nama_pegawai'] = $p->peg_nama;
            }
            $data['nip_pegawai'] = $p->peg_nip;
            $jabatan = $p->getJabatan();
            $data['jabatan'] = $jabatan['jabatan'];
            $data['foto'] = $p->getPhotoUrl();
            $now = date('Y-m-d');
            if(((date('d',strtotime($now))) >= 1) && ((date('d',strtotime($now))) <= MAX_TANGGAL_REVIEW)){
                $tanggal = MAX_TANGGAL_REVIEW + 1;
                $first = date('Y-m-'.$tanggal,strtotime("-1 months",strtotime($now)));
                $first = date('Y-m-d',strtotime($first));
                $last = date('Y-m-'.MAX_TANGGAL_REVIEW);
                $last = date('Y-m-d', strtotime($last));
            }else{
                $tanggal = MAX_TANGGAL_REVIEW + 1;
                $first = date('Y-m-'.$tanggal);
                $first = date('Y-m-d',strtotime($first));
                $last = date('Y-m-'.MAX_TANGGAL_REVIEW, strtotime("+1 months", strtotime($first)));
                $last = date('Y-m-d', strtotime($last));
            }

            $twister = new twister;

            $bulan = date("n",strtotime($first));
            $reviewer = Reviewers::where('nip',$data['nip_pegawai'])->where('nip_reviewer',$nip)->where('bulan',$bulan)->first();
            if (!$reviewer) {
                return redirect('review-perilaku');
            }
            if(($now >= $first) && ($now <= $last)){
                $stat = false;
                $jabatan = $p->getJabatan();
                $jenjab = $jabatan['jenis_jabatan'];

                if($jenjab != "S"){
                    $kelompok = ListReview::where('kelompok','!=',"KEPEMIMPINAN")->where('status_hapus',false)->groupBy('kelompok')->orderBy('kelompok','asc')->get(['kelompok']);
                }else{
                    $kelompok = ListReview::where('status_hapus',false)->groupBy('kelompok')->orderBy('kelompok','asc')->get(['kelompok']);
                }
                if (!$reviewer->submit) {
                    $no = array();
                    $num = 0;
                    $ray = 0;
                    $kel = 0;
                    foreach ($kelompok as $k) {
                        $list = ListReview::where('kelompok',$k->kelompok)->where('status_hapus',false)->orderBy('id','asc')->get();
                        $pilihan[$kel] = array();
                        $pilih[$kel] = array();
                        
                        foreach ($list as $l) {
                            $no[$kel][] = ["kelompok" => $l->kelompok,"narasi" => $l->narasi,"id" => $l->id,"nomor" => $l->nomor];
                        }
                        do {
                            //proses pengocokan pertanyaan
                            $twister->init_with_string($nip . $p->peg_nip . $bulan . $k->kelompok . $num);
                            $last = (count($list) - 1);
                            $pil[$num] = $twister->rangeint(0,$last);
                            $pilihan[$kel][$ray] = $pil[$num];
                            if(duplikat($pilihan[$kel]) == 1){
                                $pilihan[$kel] = array_map("unserialize", array_unique(array_map("serialize", $pilihan[$kel])));
                                $ray++; 
                            }else{
                                $a = $pilihan[$kel][$ray];
                                $pilih[$kel][$ray] = $no[$kel][$a];
                                $ray++;
                            }

                            $num++;
                        } while ((count($pilih[$kel])) < 3);
                        unset($pil);
                        $pil = array();
                        $ray = 0;   
                        $num = 0;
                        $stat = false;
                        $kel++; 
                    }
                    $data['cek'] = IsiReview::where('bulan',$bulan)->where('nip',$p->peg_nip)->where('nip_reviewer',$nip)->orderBy('id_review','asc')->get();
                    $pilih = collect($pilih)->flatten(1)->sortBy('id')->values();
                    $data['cek2'] = Reviewers::where('nip',$data['nip_pegawai'])->where('nip_reviewer',$nip)->where('bulan',$bulan)->first();
                    return view("pages.review-perilaku.review",compact('data','pilih'));
                } else {
                    $reviews = IsiReview::with('pertanyaan_review')->where('nip',$p->peg_nip)->where('bulan',$bulan)
                        ->where('nip_reviewer',$nip)->orderBy('id_review','asc')
                        ->get()->groupBy('pertanyaan_review.kelompok');
                    return view("pages.review-perilaku.lihat-review",compact('data','reviews','kelompok'));
                }
            }
        }else{
            return redirect('review-perilaku');
        }
    }

    public static function createVa($trx_id, $amount, $virtual_number, $name, $email, $phone)
    {
        // Fungsi Untuk Membuat Virtual Account BNI, Untuk encryptnya sendiri sudah disediakan oleh BNInya

        $bni = config('bni')[config('bni.endpoint')];

        $client_id  = $bni['client_id'];
        $secret_key = $bni['secret_key'];
        $url        = $bni['url'];

        $data_asli = [
            'client_id'        => $client_id,
            'trx_id'           => $trx_id, // fill with Billing ID
            'trx_amount'       => $amount,
            'billing_type'     => 'c',
            'datetime_expired' => date('c', time() + 6 * 3600), // billing will be expired in 1 day
            'virtual_account'  => $virtual_number,
            'customer_name'    => $name,
            'customer_email'   => $email,
            'customer_phone'   => $phone,
            'type'             => 'createBilling',
        ];

        $hashed_string = BNIEncrypt::encrypt(
            $data_asli,
            $client_id,
            $secret_key
        );

        $data = [
            'client_id' => $client_id,
            'data'      => $hashed_string,
        ];

        $response      = static::getContent($url, json_encode($data));
        $response_json = json_decode($response, true);
        if ($response_json['status'] !== '000') {
            // handling jika gagal
            return $response_json;
        } else {
            $data_response = BNIEncrypt::decrypt($response_json['data'], $client_id, $secret_key);

            return $data_response;
        }
    }

    public static function getRate($postal_to,$weight){
        // Fungsi untuk mengambil harga shipping dari RPX menggunakan NuSoap

        $client = new NuSoapClient('http://api.rpxholding.com/wsdl/rpxwsdl.php?wsdl', 'wsdl');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = FALSE;
        $error  = $client->getError();
        $username = "celcius";
        $password  = "celcius";

        $err = $client->getError();
        if ($err) {
            echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
        }

        $result = $client->call('getRatesPostalCode', array('user' => $username, 'password' => $password,'origin_postal_code' => '16457','destination_postal_code' => $postal_to,'weight' => $weight,'service_type' => 'RGP','format' => 'JSON'));

        if ($client->fault) {
            return $result;
        } else {
            $err = $client->getError();
            if ($err) {
                return $err;
            } else {
                $result = json_decode($result);
                $data = [];

                if(!is_string($result->RPX->DATA)){
                    array_push($data,['price' => $result->RPX->DATA->PRICE,'code' => 'RGP','name' => $result->RPX->DATA->SERVICE]);
                }

                return $data;
            }
        }
            
    }

    public static function getQuote($country, $city, $zip_code, $weight,$city_name)
    {
        //FUngsi Untuk mengambil harga shipping dari API DHL

        $dhl = config('dhl')[config('dhl.endpoint')];

        $sample           = new GetQuote();
        $sample->SiteID   = $dhl['id'];
        $sample->Password = $dhl['pass'];

        $sample->MessageTime      = date('c');
        $sample->MessageReference = str_random(32);
        $sample->BkgDetails->Date = date('Y-m-d', strtotime("+2 weekday"));

        $piece          = new PieceType();
        $piece->PieceID = 1;
        $piece->Height  = 10;
        $piece->Depth   = 5;
        $piece->Width   = 10;
        $piece->Weight  = $weight;
        $sample->BkgDetails->addPiece($piece);
        $sample->BkgDetails->IsDutiable         = 'Y'; // we always send item, not document
        $sample->BkgDetails->ReadyTime          = 'PT5M';
        $sample->BkgDetails->ReadyTimeGMTOffset = '+07:00';
        $sample->BkgDetails->DimensionUnit      = 'CM';
        $sample->BkgDetails->WeightUnit         = 'KG';
        $sample->BkgDetails->PaymentCountryCode = 'ID';
        $sample->BkgDetails->NetworkTypeCode    = 'TD'; // AL or TD ?
        $sample->BkgDetails->PaymentAccountNumber = $dhl['shipperAccountNumber'];

        $sample->BkgDetails->QtdShp->GlobalProductCode = 'P';

        $sample->From->CountryCode = 'ID';
        $sample->From->Postalcode  = '10150';
        $sample->From->City        = 'Jakarta';

        $sample->To->CountryCode = $country->code;
        $sample->To->Postalcode = $zip_code;
        $sample->To->City       = $city ? $city->name : $city_name;
        $sample->Dutiable->DeclaredValue    = '100.00';
        $sample->Dutiable->DeclaredCurrency = 'USD';

        // Call DHL XML API
        $start = microtime(true);
        $client = new WebserviceClient(config('dhl.endpoint'));
        $xml    = $client->call($sample);

        return static::getTotalPrice($xml);
    }

    public function confirmOrder(Request $request)
    {
        //Fungsi untuk memproses Order dilakukan dengan AJAX

        DB::beginTransaction();
        $purchase = PurchaseOrderUpdate::where('purchase_order.id', $request->id_order)->first();
        if (!$purchase) return response()->json(['success' => false, 'message' => __('homepage.notification.purchase_notfound')]);

        if ($request->id_order != $purchase->id) return response()->json(['success' => false, 'message' => __('homepage.notification.purchase_notfound')]);

        if ($purchase->company_id != Auth::user()->company_id && $purchase->company_buyer_id != Auth::user()->company_id) return response()->json(['success' => false, 'message' => 'You dont have permission to do this.']);

        if (!Auth::user()->company) {
            Mail::raw(Auth::user()->full_name . ', User ID : ' . Auth::user()->id . '. Company Not Found', function ($message) {
                $message->to('info@glexindo.com');
            });
            return response()->json(['success' => false, 'message' => 'Company Not Found']);
        }

        $purchase = PurchaseOrderUpdate::find($purchase->id);
        if ($request->is_buy) {
            if ($request->status == 'accept') {
                $purchase->status = 'waiting_for_response';
            } elseif ($request->status == 'decline') {
                $purchase->status = 'declined';
            } elseif ($request->status == 'confirm_payment') {
                $purchase->status = 'payment_receive_confirm';
            } elseif ($request->status == 'order_received') {
                $purchase->status = 'transaction_completed';
            } elseif ($request->status == 'negotiate') {
                $purchase->status = 'waiting_for_response';
            } else {
                $purchase->status = $request->status;
            }
        } else {
            if ($request->status == 'accept') {
                $purchase->status = 'confirm_payment';
            } elseif ($request->status == 'decline') {
                $purchase->status = 'declined';
            } elseif ($request->status == 'payment_dispute') {
                $purchase->status = 'payment_dispute';
            } elseif ($request->status == 'payment_received') {
                $purchase->status = 'payment_received';
            } elseif ($request->status == 'negotiate') {
                $purchase->status = 'placing_order';
            } else {
                $purchase->status = $request->status;
            }
        }
        $purchase->save();

        if (is_array($request->file)) {
            foreach ($request->file as $code => $file) {
                if (is_string($file)) continue;
                $target_dir = 'img/company/' . Auth::user()->company->folder_name . '/messages/';
                $filename   = $file->getClientOriginalName();
                $filename   = CDN::generateNewName($target_dir, $filename);
                CDN::upload($file, $target_dir, $filename);

                $completePath                 = $target_dir . $filename;
                $file                         = File::create([
                    "filename" => $filename,
                    "location" => $completePath,
                    "user_id"  => Auth::id(),
                ]);
                $pfile                        = new PurchaseFile;
                $pfile->file_id               = $file->id;
                $pfile->user_id               = Auth::user()->id;
                $pfile->company_id            = Auth::user()->company_id;
                $pfile->purchase_order_id     = $request->id_order;
                $pfile->r_purchase_files_code = $code;
                $pfile->save();
            }
        }

        $slug      = [];
        $price     = [];
        $price_old = [];
        $nego      = $request->get('product');

        if (is_string($nego)) {
            $nego = json_decode($nego);
        }
        $id_detail = [];
        foreach ($nego as $p) {
            if (is_string($p)) {
                $p = json_decode($p, true);
            }

            if (isset($p['id'])) {
                $product = PurchaseDetail::where('id', $p['id'])->first();
                if ($product) {
                    $product     = PurchaseDetail::find($product->id);
                    $price_old[] = fixDecimal($product->product_price);
                } else {
                    $product                    = new PurchaseDetail;
                    $product->product_slug      = str_replace(' ', '-', strtolower($p['name_product']));
                    $product->purchase_order_id = $request->id_order;
                    $price_old[]                = 0;
                }
            } else {
                $product = new PurchaseDetail;
                if (isset($p['product_id']) && $p['product_id'] != null) {
                    $product->product_id = $p['product_id'];
                }
                $product->product_slug      = str_replace(' ', '-', strtolower($p['name_product']));
                $product->purchase_order_id = $request->id_order;
                $price_old[]                = 0;
            }

            $product->product_quantity    = fixDecimal($p['product_quantity']);
            $product->name                = $p['name_product'];
            $product->product_price       = fixDecimal($p['product_price']);
            $product->product_description = strip_tags($p['product_description']);
            $product->unit_code           = $p['unit_code'];
            $product->save();
            $id_detail[] = $product->id;
            $price[]     = fixDecimal($p['product_price']);
            $slug[]      = str_replace(' ', '-', strtolower($p['name_product']));
        }
        $remove = PurchaseDetail::where('purchase_order_id', $request->id_order)->whereNotIn('id', $id_detail)->delete();

        $negotiation                    = new PurchaseNegotiation;
        $negotiation->purchase_order_id = $request->id_order;
        $negotiation->old_price         = json_encode($price_old);
        $negotiation->new_price         = json_encode($price);
        $negotiation->user_id           = Auth::user()->id;
        $negotiation->product_slug      = json_encode($slug);
        $negotiation->status = $purchase->status;
        $negotiation->save();

        $other = PurchaseOtherCost::where('purchase_order_id', $request->id_order)->where('slug', 'other-cost')->first();
        if (!$other) {
            $other                    = new PurchaseOtherCost;
            $other->slug              = 'other-cost';
            $other->cost_name         = 'Other Cost';
            $other->purchase_order_id = $request->id_order;
        }
        $other->price_cost = fixDecimal($request->other_cost);
        $other->save();

        $shipping = PurchaseOtherCost::where('purchase_order_id', $request->id_order)->where('slug', 'shipping-cost')->first();
        if (!$shipping) {
            $shipping                    = new PurchaseOtherCost;
            $shipping->slug              = 'shipping-cost';
            $shipping->cost_name         = 'Chipping Cost';
            $shipping->purchase_order_id = $request->id_order;
        }
        $shipping->price_cost = fixDecimal($request->shipping_cost);
        $shipping->save();

        $address = $request->get('address');
        $pad     = false;
        if (isset($address['contact_name']) && $address['contact_name'] != '') {
            if (!isset($address['id']) || (isset($address['id']) && $address['id'] == null)) {
                $pad = new PurchaseAddress;
            } else {
                $pad = PurchaseAddress::find($address['id']);
            }

            $pad->purchase_order_id = $purchase->id;
            $pad->company_id        = $purchase->company_buyer_id;
            $pad->country_id        = $address['country_id'];
            $pad->contact_name      = $address['contact_name'];
            $pad->address_1         = $address['address_1'];
            $pad->address_2         = $address['address_2'];
            $pad->city_id           = $address['city_id'];
            $pad->province_state    = $address['province_state'];
            $pad->zip_code          = $address['zip_code'];
            $pad->mobile            = $address['mobile'];
            $pad->phone_number      = $address['phone_number'];

            if ($pad->contact_name != '') {
                $pad->save();
            }
        }

        $checksipment = PurchaseShipment::where('purchase_order_id', $request->id_order)->first();
        if ($checksipment) {
            $shipment = PurchaseShipment::find($checksipment->id);
        } else {
            $shipment                    = new PurchaseShipment;
            $shipment->purchase_order_id = $request->id_order;
            $shipment->currency_code     = 'USD';
        }
        $shipment->shipping_method        = $request->shipping_type;
        $shipment->shipping_fee           = fixDecimal($request->shipping_cost);
        $shipment->logistic_insurance_fee = 0;
        $shipment->shipment_company       = $request->delivery;
        $shipment->insurance_company      = $request->insurance;
        $shipment->trade_terms            = $request->shipping_terms;
        $shipment->company_address_id     = $purchase->company_buyer_id;
        $shipment->purchase_address_id    = $pad ? $pad->id : null;
        $shipment->save();

        $checkpayment = PurchasePayment::where('purchase_order_id', $request->id_order)->first();
        if ($checkpayment) {
            $payment = PurchasePayment::find($checkpayment->id);
        } else {
            $payment                    = new PurchasePayment;
            $payment->purchase_order_id = $request->id_order;
        }
        $payment->payment_method  = $request->payment_type;
        $payment->initial_payment = fixDecimal($request->total_amount);
        $payment->ammount_payment = fixDecimal($request->total_amount);
        $payment->save();

        $log           = new PurchaseLog;
        $log->username = Auth::user()->username;
        if ($request->is_buy) {
            switch ($request->status) {
                case 'accept':
                    $log->action = 'Order has been sent to Seller';
                    break;
                case 'decline':
                    $log->action = 'Offer declined';
                    break;
                case 'negotiate':
                    $log->action = 'Negotiating Offer';
                    break;
                case 'confirm_payment':
                    $log->action = 'Payment info sent';
                    break;
                case 'order_received':
                    $log->action = 'Order received';
                    break;
                case 'order_dispute':
                    $log->action = 'Dispute order';
                    break;
            }
        } else {
            switch ($request->status) {
                case 'accept':
                    $log->action = 'Order confirmed';
                    break;
                case 'decline':
                    $log->action = 'Order declined';
                    break;
                case 'negotiate':
                    $log->action = 'Negotiating Offer';
                    break;
                case 'payment_received':
                    $log->action = 'Payment received';
                    break;
                case 'payment_dispute':
                    $log->action = 'Payment dispute';
                    break;
                case 'order_shipped':
                    $log->action = 'Order shipped';
                    break;
                case 'order_dispute_resolved':
                    $log->action = 'Order dispute resolved';
                    break;
            }
        }

        $log->entity_id                           = $request->id_order;
        $log->description                         = $negotiation;
        $log->order_status_style_icon             = $purchase->order_status_style_icon;
        $log->order_status_style_color            = $purchase->order_status_style_color;
        $log->order_status_style_background_color = $purchase->order_status_style_background_color;
        $log->save();

        $pr = PurchaseRequirement::where('purchase_order_id', $request->id_order)->first();
        if ($pr) {
            $chek = PurchaseShipment::where('purchase_order_id', $request->id_order)->first();

            $pr->requirements = $request->requirements;
            $pr->shipping     = $chek ? $chek->shipping_method : 'sea-freight';
            $pr->save();
        } else {
            
            $pr                    = new PurchaseRequirement;
            $pr->purchase_order_id = $request->id_order;

            $pr->requirements = $request->requirements;

            $chek = PurchaseShipment::where('purchase_order_id', $request->id_order)->first();

            $pr->shipping = $chek ? $chek->shipping_method : 'sea-freight';
            $pr->save();
        }

        //Disini akan dijalankan proses pengiriman Email
        event(new \App\Events\PurchaseOrder($purchase));
        DB::commit();
        $purchase->sendStatusMessage($log);

        return response()->json(['success' => true, 'message' => __('homepage.notification.update_success')]);
    }

    public function getDataProduct(Request $request)
    {
        //Fungsi untuk mengambil data produk dari database menggunakan AJAX dan filternya

        $lang   = config('translatable.search_force_english', false) ? 'en' : \App::getLocale();
        $models = ProductSearch::where('lang', $lang)
            ->where('status', 'show')->whereHas('company',function($q){
                $q->where('visibility_status','show');
            });
        $params = $request->get('params', false);
        $order  = $request->get('order', false);
        if (Auth::check()) {
            if ($company = Auth::user()->company) {
                if ($activeUserMembership = $company->active_user_membership) {
                    $membership = $activeUserMembership->membership;
                    if ($membership->style_code == 'silver') {
                        $models = $models->where(function ($q) {
                            $q->whereRaw('(level & 1) > 0')->orWhere('company_id', Auth::user()->company_id);
                        });
                    } else if ($membership->style_code == 'gold') {
                        $models = $models->where(function ($q) {
                            $q->whereRaw('(level & 2) > 0')->orWhere('company_id', Auth::user()->company_id);
                        });
                    } else if ($membership->style_code == 'platinum') {
                        $models = $models->where(function ($q) {
                            $q->whereRaw('(level & 4) > 0')->orWhere('company_id', Auth::user()->company_id);
                        });
                    } else {
                        $models = $models->where(function ($q) {
                            $q->whereRaw('(level & 128) > 0')->orWhere('company_id', Auth::user()->company_id);
                        });
                    }
                } else {
                    $models = $models->where(function ($q) {
                        $q->whereRaw('(level & 128) > 0')->orWhere('company_id', Auth::user()->company_id);
                    });
                }
            } else {
                $models = $models->where(function ($q) {
                    $q->whereRaw('(level & 128) > 0')->orWhere('company_id', Auth::user()->company_id);
                });
            }
        } else {
            $models = $models->whereRaw('(level & 128) > 0');
        }

        if ($params) {
            foreach ($params as $key => $val) {
                if ($val == '') continue;
                switch ($key) {
                    case 'company_id':
                        if ($val != null) {
                            $models = $models->where('company_id', $val);
                        }
                        break;
                    case 'category':
                        if ($val == 'category') {
                            $category = Category::where('slug', $params['id'])->first();
                            if (empty($category)) break;
                            $getAll   = Category::where(function ($q) use ($category) {
                                $q->where('level_1_id', $category->id)->orWhere('level_2_id', $category->id)->orWhere('level_3_id', $category->id)->orWhere('level_4_id', $category->id)->orWhere('level_5_id', $category->id);
                            })->pluck('id');
                            $models   = $models->whereIn('category_id', $getAll);
                        } else {
                            $search_input = explode(' ', spaceTrimmer($params['id']));
                            $search_input = implode(' & ', $search_input);
                            $search_input = stripslashes($search_input);
                            $search_input = str_replace("'", "''", $search_input);
                            $models       = $models->fromRaw("product_searches, to_tsquery('$search_input:*') AS q")
                                ->whereRaw('searchtext @@ q');
                        }
                        break;
                    case 'category_minisite':
                        if ($val != null && $val != "all") {
                            if ($val == 'ungrouped') {
                                $models = $models->whereNull('minisite_category_id');
                            } else {
                                $models = $models->where('minisite_category_id', $val);
                            }
                        }
                        break;
                    case 'id':
                        break;
                    case 'addition':
                        if ($val != null) {
                            $category = Category::where('slug', $val)->first();
                            $getAll   = Category::where(function ($q) use ($category) {
                                $q->where('level_1_id', $category->id)->orWhere('level_2_id', $category->id)->orWhere('level_3_id', $category->id)->orWhere('level_4_id', $category->id)->orWhere('level_5_id', $category->id);
                            })->pluck('id');
                            $models   = $models->whereIn('category_id', $getAll);
                        }
                        break;
                    case 'params':
                        if (count($val) == 0) continue;

                        if (isset($val['price_from']) && isset($val['price_to'])) {
                            $models = $models->where(function ($q) use ($val) {
                                $q->where('price_from', '>=', $val['price_from'])
                                    ->where('price_from', '<=', $val['price_to']);
                            });
                        } elseif (isset($val['price_from']) && !isset($val['price_to'])) {
                            $models = $models->where(function ($q) use ($val) {
                                $q->where('price_from', '>=', $val['price_from']);
                            });
                        } elseif (!isset($val['price_from']) && isset($val['price_to'])) {
                            $models = $models->where(function ($q) use ($val) {
                                $q->where('price_from', '<=', $val['price_to']);
                            });
                        }

                        if (isset($val['Country']))
                            $models = $models->whereIn('country_id', $val['Country']);

                        if (isset($val['Region']))
                            $models = $models->where('continent', $val['Region']);

                        if (isset($val['Total Revenue']))
                            $models = $models->whereIn('total_annual_revenue', $val['Total Revenue']);

                        if (isset($val['Mgnt Certification']))
                            if (is_string($val['Mgnt Certification']))
                                $models = $models->whereRaw("certification_name_id @> '{$val['Mgnt Certification']}'");
                            else {
                                $tmpVar = json_encode($val['Mgnt Certification']);
                                $models = $models->whereRaw("certification_name_id @> '$tmpVar'");
                            }

                        if (isset($val['Membership Type']))
                            $models = $models->whereIn('membership_id', $val['Membership Type']);

                        break;
                    default:
                        $models = $models->where($key, $val);
                        break;
                }
            }
        }

       $count = $models->count();

        if ($order) {
            switch ($order) {
                case 'relevance':
                    if (isset($search_input))
                        $models = $models->orderByRaw("ts_rank_cd('{0.1, 0.2, 0.4, 1.0}', searchtext, q) DESC");
                    elseif (isset($category))
                        $models = $models->orderBy('ranking', 'desc')->orderByRaw('case when category_id = ' . $category->id . ' then 1 else 2 end asc')->orderBy('created_at', 'desc');
                    else
                        $models = $models->orderBy('ranking', 'desc')->orderBy('created_at', 'desc');
                    break;
                case 'price-desc':
                    $models = $models->orderBy(DB::raw("coalesce((CASE WHEN price_from = 0 OR price_from ISNULL THEN NULL ELSE price_from END), 0)"), 'desc');
                    break;
                case 'price':
                    $models = $models->orderBy(DB::raw('(CASE WHEN price_from = 0 OR price_from ISNULL THEN NULL ELSE price_from END)'), 'asc');
                    break;
                case 'date':
                    $models = $models->orderBy('created_at', 'desc');
                    break;
                default:
                    break;
            }
        }
        $models = $models->orderBy('membership_id', 'desc')->orderBy('country_search_order', 'desc');

        $page    = $request->get('page', 1);
        $perpage = $request->get('perpage', 20);

        $models     = $models->skip(($page - 1) * $perpage)->take($perpage)->get();
        $productIds = $models->pluck('id_product')->all();

        if (count($productIds) == 0 && $page == 1) $count = 0;

        $favorites = [];
        if (Auth::check())
            $favorites = Auth::user()->favoriteProductUpdates->pluck('id')->all();

        foreach ($models as &$model) {
            $model->price_to   = removeDecimal($model->price_to);
            $model->price_from = removeDecimal($model->price_from);
            $model->favorited  = in_array($model->id_product, $favorites);
        }

        SuperCounterService::visitorStoreProductBatch($productIds, 2);

        $result = [
            'data'  => $models,
            'count' => $count,
        ];

        return response()->json($result);
    }
}
