{{-- Shared Phone Number Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'contact' => null, 'mode' => 'create'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">
        <i class="fas fa-trash"></i>
    </button>
    
    {{-- Only include ID in edit mode --}}
    @if($mode === 'edit' && $contact?->id)
        <input type="hidden" name="contact_id[{{ $index }}]" value="{{ $contact->id }}">
    @endif
    
    <div class="content-grid">
        <div class="form-group">
            <label>Type</label>
            <select name="{{ $mode === 'edit' ? 'contact_type_hidden' : 'contact_type' }}[{{ $index }}]" class="contact-type-selector">
                <option value="Personal" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Personal' ? 'selected' : '' }}>Personal</option>
                <option value="Work" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Work' ? 'selected' : '' }}>Work</option>
                <option value="Mobile" {{ ($contact->contact_type ?? old("contact_type.$index", 'Mobile')) == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                <option value="Business" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Business' ? 'selected' : '' }}>Business</option>
                <option value="Secondary" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                <option value="Father" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Father' ? 'selected' : '' }}>Father</option>
                <option value="Mother" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Mother' ? 'selected' : '' }}>Mother</option>
                <option value="Brother" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Brother' ? 'selected' : '' }}>Brother</option>
                <option value="Sister" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Sister' ? 'selected' : '' }}>Sister</option>
                <option value="Uncle" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                <option value="Aunt" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Aunt' ? 'selected' : '' }}>Aunt</option>
                <option value="Cousin" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Cousin' ? 'selected' : '' }}>Cousin</option>
                <option value="Others" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Others' ? 'selected' : '' }}>Others</option>
                <option value="Partner" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Partner' ? 'selected' : '' }}>Partner</option>
                <option value="Not In Use" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Not In Use' ? 'selected' : '' }}>Not In Use</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Number</label>
            <div class="cus_field_input flex-container">
                <div class="country_code">
                    <select name="country_code[{{ $index }}]" class="country-code-input">
                        <option value="+61" {{ ($contact->country_code ?? old("country_code.$index", '+61')) == '+61' ? 'selected' : '' }}>🇦🇺 +61</option>
                        <option value="+64" {{ ($contact->country_code ?? old("country_code.$index")) == '+64' ? 'selected' : '' }}>🇳🇿 +64</option>
                        <option value="+91" {{ ($contact->country_code ?? old("country_code.$index")) == '+91' ? 'selected' : '' }}>🇮🇳 +91</option>
                        <option value="+1" {{ ($contact->country_code ?? old("country_code.$index")) == '+1' ? 'selected' : '' }}>🇺🇸 +1</option>
                        <option value="+44" {{ ($contact->country_code ?? old("country_code.$index")) == '+44' ? 'selected' : '' }}>🇬🇧 +44</option>
                        <option value="+49" {{ ($contact->country_code ?? old("country_code.$index")) == '+49' ? 'selected' : '' }}>🇩🇪 +49</option>
                        <option value="+33" {{ ($contact->country_code ?? old("country_code.$index")) == '+33' ? 'selected' : '' }}>🇫🇷 +33</option>
                        <option value="+86" {{ ($contact->country_code ?? old("country_code.$index")) == '+86' ? 'selected' : '' }}>🇨🇳 +86</option>
                        <option value="+81" {{ ($contact->country_code ?? old("country_code.$index")) == '+81' ? 'selected' : '' }}>🇯🇵 +81</option>
                        <option value="+82" {{ ($contact->country_code ?? old("country_code.$index")) == '+82' ? 'selected' : '' }}>🇰🇷 +82</option>
                        <option value="+65" {{ ($contact->country_code ?? old("country_code.$index")) == '+65' ? 'selected' : '' }}>🇸🇬 +65</option>
                        <option value="+60" {{ ($contact->country_code ?? old("country_code.$index")) == '+60' ? 'selected' : '' }}>🇲🇾 +60</option>
                        <option value="+66" {{ ($contact->country_code ?? old("country_code.$index")) == '+66' ? 'selected' : '' }}>🇹🇭 +66</option>
                        <option value="+63" {{ ($contact->country_code ?? old("country_code.$index")) == '+63' ? 'selected' : '' }}>🇵🇭 +63</option>
                        <option value="+84" {{ ($contact->country_code ?? old("country_code.$index")) == '+84' ? 'selected' : '' }}>🇻🇳 +84</option>
                        <option value="+62" {{ ($contact->country_code ?? old("country_code.$index")) == '+62' ? 'selected' : '' }}>🇮🇩 +62</option>
                        <option value="+39" {{ ($contact->country_code ?? old("country_code.$index")) == '+39' ? 'selected' : '' }}>🇮🇹 +39</option>
                        <option value="+34" {{ ($contact->country_code ?? old("country_code.$index")) == '+34' ? 'selected' : '' }}>🇪🇸 +34</option>
                        <option value="+7" {{ ($contact->country_code ?? old("country_code.$index")) == '+7' ? 'selected' : '' }}>🇷🇺 +7</option>
                        <option value="+55" {{ ($contact->country_code ?? old("country_code.$index")) == '+55' ? 'selected' : '' }}>🇧🇷 +55</option>
                        <option value="+52" {{ ($contact->country_code ?? old("country_code.$index")) == '+52' ? 'selected' : '' }}>🇲🇽 +52</option>
                        <option value="+54" {{ ($contact->country_code ?? old("country_code.$index")) == '+54' ? 'selected' : '' }}>🇦🇷 +54</option>
                        <option value="+56" {{ ($contact->country_code ?? old("country_code.$index")) == '+56' ? 'selected' : '' }}>🇨🇱 +56</option>
                        <option value="+57" {{ ($contact->country_code ?? old("country_code.$index")) == '+57' ? 'selected' : '' }}>🇨🇴 +57</option>
                        <option value="+51" {{ ($contact->country_code ?? old("country_code.$index")) == '+51' ? 'selected' : '' }}>🇵🇪 +51</option>
                        <option value="+58" {{ ($contact->country_code ?? old("country_code.$index")) == '+58' ? 'selected' : '' }}>🇻🇪 +58</option>
                        <option value="+27" {{ ($contact->country_code ?? old("country_code.$index")) == '+27' ? 'selected' : '' }}>🇿🇦 +27</option>
                        <option value="+20" {{ ($contact->country_code ?? old("country_code.$index")) == '+20' ? 'selected' : '' }}>🇪🇬 +20</option>
                        <option value="+234" {{ ($contact->country_code ?? old("country_code.$index")) == '+234' ? 'selected' : '' }}>🇳🇬 +234</option>
                        <option value="+254" {{ ($contact->country_code ?? old("country_code.$index")) == '+254' ? 'selected' : '' }}>🇰🇪 +254</option>
                        <option value="+233" {{ ($contact->country_code ?? old("country_code.$index")) == '+233' ? 'selected' : '' }}>🇬🇭 +233</option>
                        <option value="+212" {{ ($contact->country_code ?? old("country_code.$index")) == '+212' ? 'selected' : '' }}>🇲🇦 +212</option>
                        <option value="+213" {{ ($contact->country_code ?? old("country_code.$index")) == '+213' ? 'selected' : '' }}>🇩🇿 +213</option>
                        <option value="+216" {{ ($contact->country_code ?? old("country_code.$index")) == '+216' ? 'selected' : '' }}>🇹🇳 +216</option>
                        <option value="+218" {{ ($contact->country_code ?? old("country_code.$index")) == '+218' ? 'selected' : '' }}>🇱🇾 +218</option>
                        <option value="+220" {{ ($contact->country_code ?? old("country_code.$index")) == '+220' ? 'selected' : '' }}>🇬🇲 +220</option>
                        <option value="+221" {{ ($contact->country_code ?? old("country_code.$index")) == '+221' ? 'selected' : '' }}>🇸🇳 +221</option>
                        <option value="+222" {{ ($contact->country_code ?? old("country_code.$index")) == '+222' ? 'selected' : '' }}>🇲🇷 +222</option>
                        <option value="+223" {{ ($contact->country_code ?? old("country_code.$index")) == '+223' ? 'selected' : '' }}>🇲🇱 +223</option>
                        <option value="+224" {{ ($contact->country_code ?? old("country_code.$index")) == '+224' ? 'selected' : '' }}>🇬🇳 +224</option>
                        <option value="+225" {{ ($contact->country_code ?? old("country_code.$index")) == '+225' ? 'selected' : '' }}>🇨🇮 +225</option>
                        <option value="+226" {{ ($contact->country_code ?? old("country_code.$index")) == '+226' ? 'selected' : '' }}>🇧🇫 +226</option>
                        <option value="+227" {{ ($contact->country_code ?? old("country_code.$index")) == '+227' ? 'selected' : '' }}>🇳🇪 +227</option>
                        <option value="+228" {{ ($contact->country_code ?? old("country_code.$index")) == '+228' ? 'selected' : '' }}>🇹🇬 +228</option>
                        <option value="+229" {{ ($contact->country_code ?? old("country_code.$index")) == '+229' ? 'selected' : '' }}>🇧🇯 +229</option>
                        <option value="+230" {{ ($contact->country_code ?? old("country_code.$index")) == '+230' ? 'selected' : '' }}>🇲🇺 +230</option>
                        <option value="+231" {{ ($contact->country_code ?? old("country_code.$index")) == '+231' ? 'selected' : '' }}>🇱🇷 +231</option>
                        <option value="+232" {{ ($contact->country_code ?? old("country_code.$index")) == '+232' ? 'selected' : '' }}>🇸🇱 +232</option>
                        <option value="+235" {{ ($contact->country_code ?? old("country_code.$index")) == '+235' ? 'selected' : '' }}>🇹🇩 +235</option>
                        <option value="+236" {{ ($contact->country_code ?? old("country_code.$index")) == '+236' ? 'selected' : '' }}>🇨🇫 +236</option>
                        <option value="+237" {{ ($contact->country_code ?? old("country_code.$index")) == '+237' ? 'selected' : '' }}>🇨🇲 +237</option>
                        <option value="+238" {{ ($contact->country_code ?? old("country_code.$index")) == '+238' ? 'selected' : '' }}>🇨🇻 +238</option>
                        <option value="+239" {{ ($contact->country_code ?? old("country_code.$index")) == '+239' ? 'selected' : '' }}>🇸🇹 +239</option>
                        <option value="+240" {{ ($contact->country_code ?? old("country_code.$index")) == '+240' ? 'selected' : '' }}>🇬🇶 +240</option>
                        <option value="+241" {{ ($contact->country_code ?? old("country_code.$index")) == '+241' ? 'selected' : '' }}>🇬🇦 +241</option>
                        <option value="+242" {{ ($contact->country_code ?? old("country_code.$index")) == '+242' ? 'selected' : '' }}>🇨🇬 +242</option>
                        <option value="+243" {{ ($contact->country_code ?? old("country_code.$index")) == '+243' ? 'selected' : '' }}>🇨🇩 +243</option>
                        <option value="+244" {{ ($contact->country_code ?? old("country_code.$index")) == '+244' ? 'selected' : '' }}>🇦🇴 +244</option>
                        <option value="+245" {{ ($contact->country_code ?? old("country_code.$index")) == '+245' ? 'selected' : '' }}>🇬🇼 +245</option>
                        <option value="+246" {{ ($contact->country_code ?? old("country_code.$index")) == '+246' ? 'selected' : '' }}>🇮🇴 +246</option>
                        <option value="+247" {{ ($contact->country_code ?? old("country_code.$index")) == '+247' ? 'selected' : '' }}>🇦🇨 +247</option>
                        <option value="+248" {{ ($contact->country_code ?? old("country_code.$index")) == '+248' ? 'selected' : '' }}>🇸🇨 +248</option>
                        <option value="+249" {{ ($contact->country_code ?? old("country_code.$index")) == '+249' ? 'selected' : '' }}>🇸🇩 +249</option>
                        <option value="+250" {{ ($contact->country_code ?? old("country_code.$index")) == '+250' ? 'selected' : '' }}>🇷🇼 +250</option>
                        <option value="+251" {{ ($contact->country_code ?? old("country_code.$index")) == '+251' ? 'selected' : '' }}>🇪🇹 +251</option>
                        <option value="+252" {{ ($contact->country_code ?? old("country_code.$index")) == '+252' ? 'selected' : '' }}>🇸🇴 +252</option>
                        <option value="+253" {{ ($contact->country_code ?? old("country_code.$index")) == '+253' ? 'selected' : '' }}>🇩🇯 +253</option>
                        <option value="+255" {{ ($contact->country_code ?? old("country_code.$index")) == '+255' ? 'selected' : '' }}>🇹🇿 +255</option>
                        <option value="+256" {{ ($contact->country_code ?? old("country_code.$index")) == '+256' ? 'selected' : '' }}>🇺🇬 +256</option>
                        <option value="+257" {{ ($contact->country_code ?? old("country_code.$index")) == '+257' ? 'selected' : '' }}>🇧🇮 +257</option>
                        <option value="+258" {{ ($contact->country_code ?? old("country_code.$index")) == '+258' ? 'selected' : '' }}>🇲🇿 +258</option>
                        <option value="+260" {{ ($contact->country_code ?? old("country_code.$index")) == '+260' ? 'selected' : '' }}>🇿🇲 +260</option>
                        <option value="+261" {{ ($contact->country_code ?? old("country_code.$index")) == '+261' ? 'selected' : '' }}>🇲🇬 +261</option>
                        <option value="+262" {{ ($contact->country_code ?? old("country_code.$index")) == '+262' ? 'selected' : '' }}>🇷🇪 +262</option>
                        <option value="+263" {{ ($contact->country_code ?? old("country_code.$index")) == '+263' ? 'selected' : '' }}>🇿🇼 +263</option>
                        <option value="+264" {{ ($contact->country_code ?? old("country_code.$index")) == '+264' ? 'selected' : '' }}>🇳🇦 +264</option>
                        <option value="+265" {{ ($contact->country_code ?? old("country_code.$index")) == '+265' ? 'selected' : '' }}>🇲🇼 +265</option>
                        <option value="+266" {{ ($contact->country_code ?? old("country_code.$index")) == '+266' ? 'selected' : '' }}>🇱🇸 +266</option>
                        <option value="+267" {{ ($contact->country_code ?? old("country_code.$index")) == '+267' ? 'selected' : '' }}>🇧🇼 +267</option>
                        <option value="+268" {{ ($contact->country_code ?? old("country_code.$index")) == '+268' ? 'selected' : '' }}>🇸🇿 +268</option>
                        <option value="+269" {{ ($contact->country_code ?? old("country_code.$index")) == '+269' ? 'selected' : '' }}>🇰🇲 +269</option>
                        <option value="+290" {{ ($contact->country_code ?? old("country_code.$index")) == '+290' ? 'selected' : '' }}>🇸🇭 +290</option>
                        <option value="+291" {{ ($contact->country_code ?? old("country_code.$index")) == '+291' ? 'selected' : '' }}>🇪🇷 +291</option>
                        <option value="+297" {{ ($contact->country_code ?? old("country_code.$index")) == '+297' ? 'selected' : '' }}>🇦🇼 +297</option>
                        <option value="+298" {{ ($contact->country_code ?? old("country_code.$index")) == '+298' ? 'selected' : '' }}>🇫🇴 +298</option>
                        <option value="+299" {{ ($contact->country_code ?? old("country_code.$index")) == '+299' ? 'selected' : '' }}>🇬🇱 +299</option>
                        <option value="+30" {{ ($contact->country_code ?? old("country_code.$index")) == '+30' ? 'selected' : '' }}>🇬🇷 +30</option>
                        <option value="+31" {{ ($contact->country_code ?? old("country_code.$index")) == '+31' ? 'selected' : '' }}>🇳🇱 +31</option>
                        <option value="+32" {{ ($contact->country_code ?? old("country_code.$index")) == '+32' ? 'selected' : '' }}>🇧🇪 +32</option>
                        <option value="+351" {{ ($contact->country_code ?? old("country_code.$index")) == '+351' ? 'selected' : '' }}>🇵🇹 +351</option>
                        <option value="+352" {{ ($contact->country_code ?? old("country_code.$index")) == '+352' ? 'selected' : '' }}>🇱🇺 +352</option>
                        <option value="+353" {{ ($contact->country_code ?? old("country_code.$index")) == '+353' ? 'selected' : '' }}>🇮🇪 +353</option>
                        <option value="+354" {{ ($contact->country_code ?? old("country_code.$index")) == '+354' ? 'selected' : '' }}>🇮🇸 +354</option>
                        <option value="+355" {{ ($contact->country_code ?? old("country_code.$index")) == '+355' ? 'selected' : '' }}>🇦🇱 +355</option>
                        <option value="+356" {{ ($contact->country_code ?? old("country_code.$index")) == '+356' ? 'selected' : '' }}>🇲🇹 +356</option>
                        <option value="+357" {{ ($contact->country_code ?? old("country_code.$index")) == '+357' ? 'selected' : '' }}>🇨🇾 +357</option>
                        <option value="+358" {{ ($contact->country_code ?? old("country_code.$index")) == '+358' ? 'selected' : '' }}>🇫🇮 +358</option>
                        <option value="+359" {{ ($contact->country_code ?? old("country_code.$index")) == '+359' ? 'selected' : '' }}>🇧🇬 +359</option>
                        <option value="+36" {{ ($contact->country_code ?? old("country_code.$index")) == '+36' ? 'selected' : '' }}>🇭🇺 +36</option>
                        <option value="+370" {{ ($contact->country_code ?? old("country_code.$index")) == '+370' ? 'selected' : '' }}>🇱🇹 +370</option>
                        <option value="+371" {{ ($contact->country_code ?? old("country_code.$index")) == '+371' ? 'selected' : '' }}>🇱🇻 +371</option>
                        <option value="+372" {{ ($contact->country_code ?? old("country_code.$index")) == '+372' ? 'selected' : '' }}>🇪🇪 +372</option>
                        <option value="+373" {{ ($contact->country_code ?? old("country_code.$index")) == '+373' ? 'selected' : '' }}>🇲🇩 +373</option>
                        <option value="+374" {{ ($contact->country_code ?? old("country_code.$index")) == '+374' ? 'selected' : '' }}>🇦🇲 +374</option>
                        <option value="+375" {{ ($contact->country_code ?? old("country_code.$index")) == '+375' ? 'selected' : '' }}>🇧🇾 +375</option>
                        <option value="+376" {{ ($contact->country_code ?? old("country_code.$index")) == '+376' ? 'selected' : '' }}>🇦🇩 +376</option>
                        <option value="+377" {{ ($contact->country_code ?? old("country_code.$index")) == '+377' ? 'selected' : '' }}>🇲🇨 +377</option>
                        <option value="+378" {{ ($contact->country_code ?? old("country_code.$index")) == '+378' ? 'selected' : '' }}>🇸🇲 +378</option>
                        <option value="+380" {{ ($contact->country_code ?? old("country_code.$index")) == '+380' ? 'selected' : '' }}>🇺🇦 +380</option>
                        <option value="+381" {{ ($contact->country_code ?? old("country_code.$index")) == '+381' ? 'selected' : '' }}>🇷🇸 +381</option>
                        <option value="+382" {{ ($contact->country_code ?? old("country_code.$index")) == '+382' ? 'selected' : '' }}>🇲🇪 +382</option>
                        <option value="+383" {{ ($contact->country_code ?? old("country_code.$index")) == '+383' ? 'selected' : '' }}>🇽🇰 +383</option>
                        <option value="+385" {{ ($contact->country_code ?? old("country_code.$index")) == '+385' ? 'selected' : '' }}>🇭🇷 +385</option>
                        <option value="+386" {{ ($contact->country_code ?? old("country_code.$index")) == '+386' ? 'selected' : '' }}>🇸🇮 +386</option>
                        <option value="+387" {{ ($contact->country_code ?? old("country_code.$index")) == '+387' ? 'selected' : '' }}>🇧🇦 +387</option>
                        <option value="+389" {{ ($contact->country_code ?? old("country_code.$index")) == '+389' ? 'selected' : '' }}>🇲🇰 +389</option>
                        <option value="+40" {{ ($contact->country_code ?? old("country_code.$index")) == '+40' ? 'selected' : '' }}>🇷🇴 +40</option>
                        <option value="+41" {{ ($contact->country_code ?? old("country_code.$index")) == '+41' ? 'selected' : '' }}>🇨🇭 +41</option>
                        <option value="+42" {{ ($contact->country_code ?? old("country_code.$index")) == '+42' ? 'selected' : '' }}>🇨🇿 +42</option>
                        <option value="+43" {{ ($contact->country_code ?? old("country_code.$index")) == '+43' ? 'selected' : '' }}>🇦🇹 +43</option>
                        <option value="+45" {{ ($contact->country_code ?? old("country_code.$index")) == '+45' ? 'selected' : '' }}>🇩🇰 +45</option>
                        <option value="+46" {{ ($contact->country_code ?? old("country_code.$index")) == '+46' ? 'selected' : '' }}>🇸🇪 +46</option>
                        <option value="+47" {{ ($contact->country_code ?? old("country_code.$index")) == '+47' ? 'selected' : '' }}>🇳🇴 +47</option>
                        <option value="+48" {{ ($contact->country_code ?? old("country_code.$index")) == '+48' ? 'selected' : '' }}>🇵🇱 +48</option>
                        <option value="+90" {{ ($contact->country_code ?? old("country_code.$index")) == '+90' ? 'selected' : '' }}>🇹🇷 +90</option>
                        <option value="+92" {{ ($contact->country_code ?? old("country_code.$index")) == '+92' ? 'selected' : '' }}>🇵🇰 +92</option>
                        <option value="+93" {{ ($contact->country_code ?? old("country_code.$index")) == '+93' ? 'selected' : '' }}>🇦🇫 +93</option>
                        <option value="+94" {{ ($contact->country_code ?? old("country_code.$index")) == '+94' ? 'selected' : '' }}>🇱🇰 +94</option>
                        <option value="+95" {{ ($contact->country_code ?? old("country_code.$index")) == '+95' ? 'selected' : '' }}>🇲🇲 +95</option>
                        <option value="+960" {{ ($contact->country_code ?? old("country_code.$index")) == '+960' ? 'selected' : '' }}>🇲🇻 +960</option>
                        <option value="+961" {{ ($contact->country_code ?? old("country_code.$index")) == '+961' ? 'selected' : '' }}>🇱🇧 +961</option>
                        <option value="+962" {{ ($contact->country_code ?? old("country_code.$index")) == '+962' ? 'selected' : '' }}>🇯🇴 +962</option>
                        <option value="+963" {{ ($contact->country_code ?? old("country_code.$index")) == '+963' ? 'selected' : '' }}>🇸🇾 +963</option>
                        <option value="+964" {{ ($contact->country_code ?? old("country_code.$index")) == '+964' ? 'selected' : '' }}>🇮🇶 +964</option>
                        <option value="+965" {{ ($contact->country_code ?? old("country_code.$index")) == '+965' ? 'selected' : '' }}>🇰🇼 +965</option>
                        <option value="+966" {{ ($contact->country_code ?? old("country_code.$index")) == '+966' ? 'selected' : '' }}>🇸🇦 +966</option>
                        <option value="+967" {{ ($contact->country_code ?? old("country_code.$index")) == '+967' ? 'selected' : '' }}>🇾🇪 +967</option>
                        <option value="+968" {{ ($contact->country_code ?? old("country_code.$index")) == '+968' ? 'selected' : '' }}>🇴🇲 +968</option>
                        <option value="+970" {{ ($contact->country_code ?? old("country_code.$index")) == '+970' ? 'selected' : '' }}>🇵🇸 +970</option>
                        <option value="+971" {{ ($contact->country_code ?? old("country_code.$index")) == '+971' ? 'selected' : '' }}>🇦🇪 +971</option>
                        <option value="+972" {{ ($contact->country_code ?? old("country_code.$index")) == '+972' ? 'selected' : '' }}>🇮🇱 +972</option>
                        <option value="+973" {{ ($contact->country_code ?? old("country_code.$index")) == '+973' ? 'selected' : '' }}>🇧🇭 +973</option>
                        <option value="+974" {{ ($contact->country_code ?? old("country_code.$index")) == '+974' ? 'selected' : '' }}>🇶🇦 +974</option>
                        <option value="+975" {{ ($contact->country_code ?? old("country_code.$index")) == '+975' ? 'selected' : '' }}>🇧🇹 +975</option>
                        <option value="+976" {{ ($contact->country_code ?? old("country_code.$index")) == '+976' ? 'selected' : '' }}>🇲🇳 +976</option>
                        <option value="+977" {{ ($contact->country_code ?? old("country_code.$index")) == '+977' ? 'selected' : '' }}>🇳🇵 +977</option>
                        <option value="+992" {{ ($contact->country_code ?? old("country_code.$index")) == '+992' ? 'selected' : '' }}>🇹🇯 +992</option>
                        <option value="+993" {{ ($contact->country_code ?? old("country_code.$index")) == '+993' ? 'selected' : '' }}>🇹🇲 +993</option>
                        <option value="+994" {{ ($contact->country_code ?? old("country_code.$index")) == '+994' ? 'selected' : '' }}>🇦🇿 +994</option>
                        <option value="+995" {{ ($contact->country_code ?? old("country_code.$index")) == '+995' ? 'selected' : '' }}>🇬🇪 +995</option>
                        <option value="+996" {{ ($contact->country_code ?? old("country_code.$index")) == '+996' ? 'selected' : '' }}>🇰🇬 +996</option>
                        <option value="+998" {{ ($contact->country_code ?? old("country_code.$index")) == '+998' ? 'selected' : '' }}>🇺🇿 +998</option>
                    </select>
                </div>
                <input type="tel" 
                       name="phone[{{ $index }}]" 
                       value="{{ $contact->phone ?? old("phone.$index") }}" 
                       placeholder="Phone Number" 
                       class="phone-number-input phone-width" 
                       autocomplete="off">
            </div>
        </div>
    </div>
</div>

