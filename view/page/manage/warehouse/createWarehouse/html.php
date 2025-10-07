
<!doctype html>
<html>

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <meta name="description" content="Online API for Viet Nam administrative divisions; API tỉnh thành Việt Nam" />
  <meta name="keywords"
    content="api, online, tool, vietnam, open, data, province, administrative, divisions, tỉnh thành, quận huyện, phường xã" />
  <meta property="og:title" content="Online API for Viet Nam administrative divisions" />
  <meta property="og:image" content="https://provinces.open-api.vn/img/vn-hsts.png" />
  <meta name="google-site-verification" content="qO-FwiRjzGujttPltH5NKj4flEZry-nZgd8YtCgYhnk" />
  <link rel="icon" href="https://provinces.open-api.vn/img/map.svg" />
  <link rel="stylesheet" href="https://provinces.open-api.vn/css/uno.css?v=2.2.5" />
  <link rel="stylesheet" href="https://provinces.open-api.vn/css/site.css" />
  <link
    href="https://fonts.googleapis.com/css2?family=Niramit:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600;1,700&display=swap"
    rel="stylesheet" />
  <title> Province Open API - Examples</title>
  <style>
    body {
      font-family: "Niramit", sans-serif;
    }

    html {
      font-size: 18px;
    }
  </style>
</head>

<body class="min-h-screen flex flex-col m-0" lang="vi">
  <header class="text-gray-600 body-font">
    <div class="lg:max-w-240 mx-auto flex flex-wrap p-5 flex-col md:flex-row items-center">
      <a href="/"
        class="flex title-font font-medium items-center text-gray-900 hover:text-indigo-600 mb-4 md:mb-0 no-underline">
        <img src="https://provinces.open-api.vn/img/voa.svg" class="w-10 h-10" />
        <span class="ml-3 text-xl">Province</span>
      </a>
      <nav class="md:ml-auto flex flex-wrap items-center text-base justify-center space-x-4">
        <a href="/api/v1/redoc" class="no-underline hover:text-gray-900">Tài liệu</a>
        <a href="/examples" class="no-underline hover:text-gray-900">Ứng dụng mẫu</a>
        <a href="https://github.com/hongquan/vn-open-api-provinces/" class="no-underline hover:text-gray-900">Mã
          nguồn</a>
      </nav>
    </div>
  </header>

  
<main class="container mx-auto px-5 flex flex-col flex-1">
  <h1 class="title-font sm:text-4xl text-3xl mb-4 font-medium text-gray-900">
    Ví dụ
  </h1>
  <article class="mt-6">
    <h2 class="text-2xl">Python</h2>
    <p class="mt-4">
      Ứng dụng Python nên dùng thư viện
      <a href="https://pypi.org/project/vietnam-provinces/" class="underline text-blue-700">VietnamProvinces</a>
      hơn là API này.
    </p>
  </article>
  <article class="mt-6">
    <h2 class="text-2xl">JavaScript (ở backend-end)</h2>
    <p class="mt-4">
      Với back-end, sử dụng API này thì không hiệu quả lắm (do độ trễ của
      việc gọi API từ xa). Tuy nhiên, có thể trong tương lai dự án
      <a href="https://pypi.org/project/vietnam-provinces/" class="underline text-blue-700">VietnamProvinces</a>
      sẽ được dùng để sinh code cho TypeScript và sẽ có một bản port cho
      JS ở back-end.
    </p>
    <em>Mẹo:</em> Bạn có thể lấy dữ liệu dạng JSON bằng cách lưu lại nội
    dung trả về của
    <a class="underline text-teal-500" href="/api/v1/?depth=3">API</a>.
  </article>
  <article class="mt-6">
    <h2 class="text-2xl">JavaScript (ở front-end)</h2>
    <p class="mt-4">
      Xem code qua chức năng "View Page Source" của trình duyệt, hoặc trên
      kho
      <a href="https://github.com/hongquan/vn-open-api-provinces/blob/main/templates/examples.html"
        class="underline">GitHub</a>. Code được viết bằng ngôn ngữ JavaScript:
    </p>
    <ul class="list-disc list-inside">
      <li>
        Theo cú pháp
        <a href="https://2ality.com/2016/02/ecmascript-2017.html" class="underline">ECMAScript 2017</a>.
      </li>
      <li>
        Sử dụng framework
        <a href="https://alpinejs.dev/" class="underline">AlpineJS</a>.
      </li>
    </ul>
    <div x-data="formApp" class="mt-4">
      <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
        <div class="relative" @click.outside="provinceListShown = false">
          <input class="p-1 px-2 appearance-none outline-none text-gray-800 border" x-model.trim="provinceSearch"
            placeholder="Tỉnh..." @focus="startSearchingProvince" />

          <div class="absolute z-10 max-h-48 w-full bg-gray-100 overflow-y-auto shadow"
            x-show="provinceListShown && filteredProvinces.length">
            <ul class="list-none">
              <template x-for="(item, idx) of filteredProvinces" :key="idx">
                <li x-html="highlightName(item)" class="px-2 py-1 cursor-pointer bg-white hover:bg-blue-100"
                  @click="selectProvince(item)"></li>
              </template>
            </ul>
          </div>
        </div>
        <div class="relative" @click.outside="districtListShown = false">
          <input class="p-1 px-2 appearance-none outline-none text-gray-800 border" x-model.trim="districtSearch"
            placeholder="Huyện..." @focus="startSearchingDistrict" @input.debounce="searchDistrictOnTyping" />

          <div class="absolute z-10 max-h-48 w-full bg-gray-100 overflow-y-auto shadow"
            x-show="districtListShown && filteredDistricts.length">
            <ul class="list-none">
              <template x-for="(item, idx) of filteredDistricts" :key="idx">
                <li x-html="highlightName(item)" class="px-2 py-1 cursor-pointer bg-white hover:bg-blue-100"
                  @click="selectDistrict(item)"></li>
              </template>
            </ul>
          </div>
        </div>
        <div class="relative" @click.outside="wardListShown = false">
          <input class="p-1 px-2 appearance-none outline-none text-gray-800 border" x-model.trim="wardSearch"
            placeholder="Xã..." @focus="startSearchingWard" />

          <div class="absolute z-10 max-h-48 w-full bg-gray-100 overflow-y-auto shadow"
            x-show="wardListShown && filteredWards.length">
            <ul class="list-none">
              <template x-for="(item, idx) of filteredWards" :key="idx">
                <li x-html="highlightName(item)" class="px-2 py-1 cursor-pointer bg-white hover:bg-blue-100"
                  @click="selectWard(item)"></li>
              </template>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </article>
  <article class="mt-6">
    <h2 class="text-2xl">VueJS (front-end)</h2>
    <p>
      Code:
      <a class="text-gray-500 hover:text-blue-600" href="https://github.com/hongquan/vn-provinces-vue-demo">
        <svg xmlns="http://www.w3.org/2000/svg" data-name="Layer 1" viewBox="0 0 24 24" class="w-5 h-5 inline-block"
          fill="currentColor">
          <path
            d="M12,2.2467A10.00042,10.00042,0,0,0,8.83752,21.73419c.5.08752.6875-.21247.6875-.475,0-.23749-.01251-1.025-.01251-1.86249C7,19.85919,6.35,18.78423,6.15,18.22173A3.636,3.636,0,0,0,5.125,16.8092c-.35-.1875-.85-.65-.01251-.66248A2.00117,2.00117,0,0,1,6.65,17.17169a2.13742,2.13742,0,0,0,2.91248.825A2.10376,2.10376,0,0,1,10.2,16.65923c-2.225-.25-4.55-1.11254-4.55-4.9375a3.89187,3.89187,0,0,1,1.025-2.6875,3.59373,3.59373,0,0,1,.1-2.65s.83747-.26251,2.75,1.025a9.42747,9.42747,0,0,1,5,0c1.91248-1.3,2.75-1.025,2.75-1.025a3.59323,3.59323,0,0,1,.1,2.65,3.869,3.869,0,0,1,1.025,2.6875c0,3.83747-2.33752,4.6875-4.5625,4.9375a2.36814,2.36814,0,0,1,.675,1.85c0,1.33752-.01251,2.41248-.01251,2.75,0,.26251.1875.575.6875.475A10.0053,10.0053,0,0,0,12,2.2467Z" />
        </svg>
        hongquan/vn-provinces-vue-demo
      </a>
    </p>
    <p>
      Demo:
      <a class="text-gray-500 hover:text-blue-600"
        href="https://vn-provinces-vue-demo.netlify.app">vn-provinces-vue-demo.netlify.app</a>
    </p>
    <p class="italic">
      Ứng dụng mẫu này được cố ý triển khai trên trang khác để kiểm tra
      CORS.
    </p>
  </article>
</main>


  <footer class="text-gray-600 body-font">
    <div class="lg:max-w-240 px-5 py-8 mx-auto flex sm:items-center sm:flex-row flex-col">
      <a class="flex title-font font-medium items-center md:justify-start justify-center text-gray-900 hover:text-green-700 min-w-36"
        href="https://portal.open-api.vn/">
        <img src="https://provinces.open-api.vn/img/voa.svg" class="w-10 h-10" />
        <span class="ml-3 text-xl">Vietnam Open API</span>
      </a>
      <div class="text-sm text-gray-500 sm:ml-4 sm:pl-4 sm:border-l-2 sm:border-gray-200 sm:py-2 sm:mt-0 mt-4">
        <p>
          © 2021
          <a href="https://quan.hoabinh.vn" class="text-blue-600 hover:text-pink-400">Nguyễn Hồng Quân</a>
        </p>
        <p>
          Icon được cho bởi
          <a href="https://www.flaticon.com/">flaticon</a>.
        </p>
        <p>
          Hình bản đồ Vietnam được cho bởi
          <a href="https://vemaps.com/vietnam/vn-07">vemaps.com</a>
          (với chút thay đổi).
        </p>
      </div>
      <div class="text-sm text-gray-500 sm:ml-4 sm:pl-4 sm:border-l-2 sm:border-gray-200 sm:py-2 sm:mt-0 mt-4 min-w-32">
        Tài trợ hosting:
        <div class="flex flex-row sm:flex-col items-start sm:items-center mt-1">
          <img src="https://omzcloud.vn/images/icon/omz-logo.png" class="h-8 w-auto" />
          <div>
            <a href="https://omzcloud.vn/" class="hover:underline">OMZCloud</a>
          </div>
        </div>
      </div>
      <div class="text-sm text-gray-500 sm:ml-4 sm:pl-4 sm:border-l-2 sm:border-gray-200 sm:py-2 sm:mt-0 mt-4">
        <h3 class="font-bold">Thưởng công</h3>
        Nếu bạn muốn thưởng cho những cố gắng thâu đêm của tôi, bạn
        có thể ủng hộ đến số đt 0939030338 qua ví điện tử:
        <p>
          <a class="text-pink-600" href="https://nhantien.momo.vn/0939030338">Momo</a>,
          <a class="text-green-600"
            href="https://grab.onelink.me/2695613898?af_dp=grab%3A%2F%2Fopen%3FscreenType%3DPEERTRANSFER%26method%3DQRCode%26pairingInfo%3DGPTransfer2956176f071847f98a155a1bedc87783">Grab
            Moca</a>,
          <a class="text-blue-600" href="https://provinces.open-api.vn/img/Quan-ZaloPay.jpg">Zalo Pay</a>,
          <a class="text-orange-600" href="https://provinces.open-api.vn/img/Quan-ShopeePay.jpg">Shopee Pay</a>.
        </p>
      </div>
      <span class="inline-flex sm:ml-4 sm:mt-0 mt-4 justify-center sm:justify-start">
        <a class="text-gray-500 hover:text-blue-600" href="https://github.com/hongquan/">
          <svg xmlns="http://www.w3.org/2000/svg" data-name="Layer 1" viewBox="0 0 24 24" class="w-5 h-5"
            fill="currentColor">
            <path
              d="M12,2.2467A10.00042,10.00042,0,0,0,8.83752,21.73419c.5.08752.6875-.21247.6875-.475,0-.23749-.01251-1.025-.01251-1.86249C7,19.85919,6.35,18.78423,6.15,18.22173A3.636,3.636,0,0,0,5.125,16.8092c-.35-.1875-.85-.65-.01251-.66248A2.00117,2.00117,0,0,1,6.65,17.17169a2.13742,2.13742,0,0,0,2.91248.825A2.10376,2.10376,0,0,1,10.2,16.65923c-2.225-.25-4.55-1.11254-4.55-4.9375a3.89187,3.89187,0,0,1,1.025-2.6875,3.59373,3.59373,0,0,1,.1-2.65s.83747-.26251,2.75,1.025a9.42747,9.42747,0,0,1,5,0c1.91248-1.3,2.75-1.025,2.75-1.025a3.59323,3.59323,0,0,1,.1,2.65,3.869,3.869,0,0,1,1.025,2.6875c0,3.83747-2.33752,4.6875-4.5625,4.9375a2.36814,2.36814,0,0,1,.675,1.85c0,1.33752-.01251,2.41248-.01251,2.75,0,.26251.1875.575.6875.475A10.0053,10.0053,0,0,0,12,2.2467Z" />
          </svg>
        </a>
        <a class="ml-3 text-gray-500 hover:text-blue-600" href="https://facebook.com/ng.hong.quan">
          <svg fill="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" class="w-5 h-5"
            viewBox="0 0 24 24">
            <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path>
          </svg>
        </a>
        <a class="ml-3 text-gray-500 hover:text-blue-600" href="https://www.linkedin.com/in/bachkhois">
          <svg fill="currentColor" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="0"
            class="w-5 h-5" viewBox="0 0 24 24">
            <path stroke="none"
              d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"></path>
            <circle cx="4" cy="4" r="2" stroke="none"></circle>
          </svg>
        </a>
      </span>
    </div>
  </footer>

   
<script type="module">
  import ky from "https://unpkg.com/ky/distribution/index.js";
  import Alpine from "https://unpkg.com/alpinejs@3.14.9/dist/module.esm.min.js";
  import "https://unpkg.com/superstruct@2.0.2/dist/index.cjs";
  const {
    object,
    number,
    string,
    array,
    record,
    defaulted,
    optional,
    size,
    mask,
    assign,
  } = window.Superstruct;

  window.Alpine = Alpine;

  const SearchMatches = record(string(), size(array(number()), 2));

  const Base = object({
    code: number(),
    name: string(),
    matches: optional(SearchMatches),
  });

  const Ward = Base;

  const District = assign(
    Base,
    object({
      wards: defaulted(array(Ward), []),
    }),
  );

  const Province = assign(
    Base,
    object({
      districts: defaulted(array(District), []),
    }),
  );

  /*
   * The Lunr engine consider each keyword optional, but in the context of
   * this demo, we want all keywords to be present.
   * This function is to prefix all keywords with plus ("+"),
   * ex: "Bà Rịa" -> "+Bà +Rịa".
   */
  function markRequireAll(query) {
    const words = query.split(/\s+/);
    return words.map((w) => `+${w}`).join(" ");
  }

  async function fetchProvinces(app) {
    const rdata = await ky.get("/api/v1/p/").json();
    app.filteredProvinces = mask(rdata, array(Province));
  }

  async function fetchDistricts(provinceCode, app) {
    const rdata = await ky
      .get(`/api/v1/p/${provinceCode}`, {searchParams: {depth: 2}})
      .json();
    const province = mask(rdata, Province);
    app.filteredDistricts = province.districts;
  }

  async function fetchWards(districtCode, app) {
    const rdata = await ky
      .get(`/api/v1/d/${districtCode}`, {searchParams: {depth: 2}})
      .json();
    const district = mask(rdata, District);
    app.filteredWards = district.wards;
  }

  async function searchProvince(term, app) {
    if (app.selectedProvince && app.selectedProvince.name === term) {
      return;
    }
    const rdata = await ky
      .get("/api/v1/p/search/", {
        searchParams: {q: markRequireAll(term)},
      })
      .json();
    app.filteredProvinces = mask(rdata, array(Province));
  }

  async function searchDistrict(term, provinceCode, app) {
    if (app.selectedDistrict && app.selectedDistrict.name === term) {
      return;
    }
    const rdata = await ky
      .get("/api/v1/d/search/", {
        searchParams: {q: markRequireAll(term), p: provinceCode},
      })
      .json();
    app.filteredDistricts = mask(rdata, array(District));
  }

  async function searchWard(term, districtCode, app) {
    if (app.selectedWard && app.selectedWard.name === term) {
      return;
    }
    const rdata = await ky
      .get("/api/v1/w/search/", {
        searchParams: {q: markRequireAll(term), d: districtCode},
      })
      .json();
    app.filteredWards = mask(rdata, array(Ward));
  }

  Alpine.data("formApp", () => ({
    provinceSearch: "",
    provinceListShown: false,
    filteredProvinces: [],
    selectedProvince: null,
    districtSearch: "",
    districtListShown: false,
    filteredDistricts: [],
    selectedDistrict: null,
    wardSearch: "",
    wardListShown: false,
    filteredWards: [],
    selectedWard: null,

    async fetchProvinces() {
      fetchProvinces(this);
    },
    resetProvince() {
      this.provinceSearch = "";
      this.selectedProvince = null;
      this.filteredProvinces = [];
      this.provinceListShown = false;
    },
    resetDistrict() {
      this.districtSearch = "";
      this.selectedDistrict = null;
      this.filteredDistricts = [];
      this.districtListShown = false;
    },
    resetWard() {
      this.wardSearch = "";
      this.selectedWard = null;
      this.filteredWards = [];
      this.wardListShown = false;
    },
    highlightName(item) {
      if (!item.matches) {
        return item.name;
      }
      const name = item.name;
      const matches = Object.values(item.matches);
      matches.sort((v1, v2) => v1[0] - v2[0]);
      const parts = [];
      var lastPos = 0;
      for (const [s, e] of matches) {
        parts.push(name.slice(lastPos, s));
        parts.push(`<strong>${name.slice(s, e)}</strong>`);
        lastPos = e;
      }
      parts.push(name.slice(lastPos));
      return parts.join("");
    },
    async startSearchingProvince() {
      this.provinceListShown = true;
      if (!this.filteredProvinces.length) {
        fetchProvinces(this);
      }
    },
    async startSearchingDistrict() {
      this.districtListShown = true;
      if (this.filteredDistricts.length || !this.selectedProvince) {
        return;
      }
      await fetchDistricts(this.selectedProvince.code, this);
    },
    async searchDistrictOnTyping() {
      const term = this.districtSearch.trim();
      if (!term || !this.selectedProvince) {
        return;
      }
      await searchDistrict(term, this.selectedProvince.code, this);
    },
    async startSearchingWard() {
      this.wardListShown = true;
      if (this.filteredWards.length || !this.selectedDistrict) {
        return;
      }
      await fetchWards(this.selectedDistrict.code, this);
    },
    selectProvince(province) {
      this.provinceListShown = false;
      this.selectedProvince = province;
      this.provinceSearch = province.name;
      this.resetDistrict();
      this.resetWard();
    },
    selectDistrict(district) {
      this.districtListShown = false;
      this.selectedDistrict = district;
      this.districtSearch = district.name;
      this.resetWard();
    },
    selectWard(ward) {
      this.wardListShown = false;
      this.selectedWard = ward;
      this.wardSearch = ward.name;
    },
    async init() {
      this.$watch("provinceSearch", async (value) => {
        const term = value.trim();
        if (!term) {
          return;
        }
        await searchProvince(value, this);
      });

      this.$watch("wardSearch", async (value) => {
        const term = value.trim();
        if (!term || !this.selectedDistrict) {
          return;
        }
        await searchWard(value, this.selectedDistrict.code, this);
      });

      await fetchProvinces(this);
    },
  }));
  Alpine.start();
</script>

</body>

</html>